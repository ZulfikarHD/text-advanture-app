<?php

namespace Tests\Feature\Sessions;

use App\Enums\EventType;
use App\Enums\StateNode;
use App\Models\Beat;
use App\Models\Chapter;
use App\Models\Character;
use App\Models\PlaySession;
use App\Models\Scene;
use App\Models\Story;
use App\Models\User;
use App\Services\SceneLogService;
use App\Services\SessionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

/**
 * Feature tests for the playable Writing/Play loop (S-5.1.1 / S-5.4.1).
 *
 * Covers the player-facing half of the loop the Writing page drives: submitting
 * input records it to the scene log and hands the turn back to the narrator
 * atomically (a failed mid-turn write rolls the whole moment back); acting
 * off-turn is rejected with the save unchanged; "continue" closes a finished beat
 * and resumes at the next, holding at the end of the story; and the Play page
 * exposes the scene-log timeline, codex, and flow it renders.
 */
class PlayLoopTest extends TestCase
{
    use RefreshDatabase;

    public function test_submitting_input_records_it_to_the_scene_log(): void
    {
        $user = User::factory()->create();
        [$story, $save, $beat] = $this->sceneSave($user, StateNode::PlayerMoment);

        $this->actingAs($user)->post(route('stories.saves.input', [$story, $save]), [
            'content' => 'I step through the doorway.',
        ]);

        $this->assertDatabaseHas('events', [
            'session_id' => $save->id,
            'beat_id' => $beat->id,
            'type' => EventType::PlayerInput->value,
            'content' => 'I step through the doorway.',
        ]);
    }

    public function test_submitting_input_hands_the_turn_back_to_the_narrator(): void
    {
        $user = User::factory()->create();
        [$story, $save] = $this->sceneSave($user, StateNode::PlayerMoment);

        $this->actingAs($user)->post(route('stories.saves.input', [$story, $save]), [
            'content' => 'I wave back.',
        ]);

        $this->assertSame(StateNode::NarratorTurn, $save->fresh()->state_node);
    }

    public function test_input_off_turn_is_rejected_and_logs_nothing(): void
    {
        $user = User::factory()->create();
        [$story, $save] = $this->sceneSave($user, StateNode::NarratorTurn);

        $this->actingAs($user)->post(route('stories.saves.input', [$story, $save]), [
            'content' => 'I act out of turn.',
        ]);

        $this->assertSame(StateNode::NarratorTurn, $save->fresh()->state_node);
        $this->assertDatabaseCount('events', 0);
    }

    public function test_input_requires_content(): void
    {
        $user = User::factory()->create();
        [$story, $save] = $this->sceneSave($user, StateNode::PlayerMoment);

        $response = $this->actingAs($user)->post(route('stories.saves.input', [$story, $save]), [
            'content' => '',
        ]);

        $response->assertSessionHasErrors('content');
    }

    public function test_a_failed_input_write_rolls_back_the_whole_player_moment(): void
    {
        $user = User::factory()->create();
        [, $save] = $this->sceneSave($user, StateNode::PlayerMoment);

        // A scene-log write that fails mid-turn must take the hand-off with it:
        // the save stays on player_moment with nothing recorded (atomic, S-5.1.1).
        $this->mock(SceneLogService::class)
            ->shouldReceive('recordPlayerInput')
            ->once()
            ->andThrow(new RuntimeException('scene log write failed'));

        try {
            app(SessionService::class)->recordPlayerMoment($save, 'I reach for the latch.');
            $this->fail('Expected the failed scene-log write to bubble up.');
        } catch (RuntimeException) {
            // The failure is expected; the assertions below prove the rollback.
        }

        $this->assertSame(StateNode::PlayerMoment, $save->fresh()->state_node);
        $this->assertDatabaseCount('events', 0);
    }

    public function test_continue_closes_a_finished_beat_and_resumes_at_the_next(): void
    {
        $user = User::factory()->create();
        [$story, $save, , $beatTwo] = $this->twoBeatSave($user, StateNode::BeatComplete);

        $this->actingAs($user)->post(route('stories.saves.continue', [$story, $save]));

        $fresh = $save->fresh();
        $this->assertSame(StateNode::NarratorTurn, $fresh->state_node);
        $this->assertSame($beatTwo->id, $fresh->current_beat_id);
    }

    public function test_continue_at_the_final_beat_holds_at_the_end(): void
    {
        $user = User::factory()->create();
        [$story, $save] = $this->sceneSave($user, StateNode::BeatComplete);

        $this->actingAs($user)->post(route('stories.saves.continue', [$story, $save]));

        $this->assertSame(StateNode::BeatComplete, $save->fresh()->state_node);
    }

    public function test_the_play_page_renders_the_scene_log_timeline(): void
    {
        $user = User::factory()->create();
        [$story, $save, $beat] = $this->sceneSave($user, StateNode::PlayerMoment);
        $save->events()->create([
            'beat_id' => $beat->id,
            'type' => EventType::Narration,
            'content' => 'The door creaks open.',
        ]);

        $response = $this->actingAs($user)->get(route('stories.saves.play', [$story, $save]));

        $response->assertInertia(fn ($page) => $page
            ->component('sessions/Play')
            ->where('timeline.0.type', EventType::Narration->value)
            ->where('timeline.0.content', 'The door creaks open.')
        );
    }

    public function test_the_play_page_flags_the_player_moment_in_its_flow(): void
    {
        $user = User::factory()->create();
        [$story, $save] = $this->sceneSave($user, StateNode::PlayerMoment);

        $response = $this->actingAs($user)->get(route('stories.saves.play', [$story, $save]));

        $response->assertInertia(fn ($page) => $page
            ->component('sessions/Play')
            ->where('flow.awaitingPlayer', true)
        );
    }

    public function test_the_play_page_exposes_the_codex_cast(): void
    {
        $user = User::factory()->create();
        [$story, $save] = $this->sceneSave($user, StateNode::NarratorTurn);

        $response = $this->actingAs($user)->get(route('stories.saves.play', [$story, $save]));

        $response->assertInertia(fn ($page) => $page
            ->component('sessions/Play')
            ->has('codex.characters', 1)
        );
    }

    /**
     * Build a story with one beat and a save positioned on it at a given node.
     *
     * @param  User  $user  The owner.
     * @param  StateNode  $state  The loop node the save sits on.
     * @return array{0: Story, 1: PlaySession, 2: Beat}
     */
    private function sceneSave(User $user, StateNode $state): array
    {
        $story = Story::factory()->create(['user_id' => $user->id]);
        Character::factory()->create(['story_id' => $story->id]);
        $chapter = Chapter::factory()->create(['story_id' => $story->id, 'number' => 1]);
        $scene = Scene::factory()->create(['chapter_id' => $chapter->id, 'number' => 1]);
        $beat = Beat::factory()->create(['scene_id' => $scene->id, 'number' => 1]);

        $save = $story->playSessions()->create([
            'name' => 'Playthrough 1',
            'state_node' => $state,
            'current_chapter_id' => $chapter->id,
            'current_scene_id' => $scene->id,
            'current_beat_id' => $beat->id,
            'last_played_at' => now(),
        ]);

        return [$story, $save, $beat];
    }

    /**
     * Build a story with two beats in one scene and a save on the first.
     *
     * @param  User  $user  The owner.
     * @param  StateNode  $state  The loop node the save sits on.
     * @return array{0: Story, 1: PlaySession, 2: Beat, 3: Beat}
     */
    private function twoBeatSave(User $user, StateNode $state): array
    {
        $story = Story::factory()->create(['user_id' => $user->id]);
        Character::factory()->create(['story_id' => $story->id]);
        $chapter = Chapter::factory()->create(['story_id' => $story->id, 'number' => 1]);
        $scene = Scene::factory()->create(['chapter_id' => $chapter->id, 'number' => 1]);
        $beatOne = Beat::factory()->create(['scene_id' => $scene->id, 'number' => 1]);
        $beatTwo = Beat::factory()->create(['scene_id' => $scene->id, 'number' => 2]);

        $save = $story->playSessions()->create([
            'name' => 'Playthrough 1',
            'state_node' => $state,
            'current_chapter_id' => $chapter->id,
            'current_scene_id' => $scene->id,
            'current_beat_id' => $beatOne->id,
            'last_played_at' => now(),
        ]);

        return [$story, $save, $beatOne, $beatTwo];
    }
}
