<?php

namespace Tests\Feature\Sessions;

use App\Enums\LlmRole;
use App\Enums\ModelScope;
use App\Enums\StateNode;
use App\Models\Beat;
use App\Models\Chapter;
use App\Models\Character;
use App\Models\ModelProfile;
use App\Models\PlaySession;
use App\Models\Scene;
use App\Models\Story;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Feature tests for multi-save management + loop-state resume (S-2.1.2 / S-2.1.3).
 *
 * Covers: naming a save on create (and the auto default); rename; reset back to
 * the freshly-forked state (position + cleared loop counters); delete; the
 * independence guarantee (managing one save never touches a sibling or the
 * authoring template); load-as-resume stamping `last_played_at`; resume
 * restoring the exact persisted loop position; owner-scoping (foreign story /
 * cross-story save both 404); and the auth gate on every write.
 */
class SessionSaveManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_start_a_save_with_a_custom_name(): void
    {
        $user = User::factory()->create();
        [$story] = $this->playReadyStory($user);

        $this->actingAs($user)->post(route('stories.saves.store', $story), ['name' => 'Chaos run']);

        $this->assertSame('Chaos run', $story->playSessions()->firstOrFail()->name);
    }

    public function test_a_save_without_a_name_falls_back_to_the_default(): void
    {
        $user = User::factory()->create();
        [$story] = $this->playReadyStory($user);

        $this->actingAs($user)->post(route('stories.saves.store', $story));

        $this->assertSame('Playthrough 1', $story->playSessions()->firstOrFail()->name);
    }

    public function test_owner_can_rename_a_save(): void
    {
        $user = User::factory()->create();
        [$story, $save] = $this->storyWithSave($user);

        $this->actingAs($user)->put(route('stories.saves.update', [$story, $save]), ['name' => 'Renamed run']);

        $this->assertSame('Renamed run', $save->fresh()->name);
    }

    public function test_renaming_requires_a_name(): void
    {
        $user = User::factory()->create();
        [$story, $save] = $this->storyWithSave($user);

        $response = $this->actingAs($user)->put(route('stories.saves.update', [$story, $save]), ['name' => '']);

        $response->assertSessionHasErrors('name');
        $this->assertSame('Playthrough 1', $save->fresh()->name);
    }

    public function test_resetting_a_save_returns_it_to_session_start(): void
    {
        $user = User::factory()->create();
        [$story, $save] = $this->storyWithSave($user);
        $save->update(['state_node' => StateNode::NarratorTurn]);

        $this->actingAs($user)->post(route('stories.saves.reset', [$story, $save]));

        $this->assertSame(StateNode::SessionStart, $save->fresh()->state_node);
    }

    public function test_resetting_a_save_repositions_to_the_first_beat(): void
    {
        $user = User::factory()->create();
        [$story, $save, $firstBeat] = $this->storyWithSave($user);
        $save->update(['current_beat_id' => null, 'current_scene_id' => null, 'current_chapter_id' => null]);

        $this->actingAs($user)->post(route('stories.saves.reset', [$story, $save]));

        $this->assertSame($firstBeat->id, $save->fresh()->current_beat_id);
    }

    public function test_resetting_a_save_clears_the_loop_state_counters(): void
    {
        $user = User::factory()->create();
        [$story, $save] = $this->storyWithSave($user);
        $save->update([
            'beat_word_count' => 480,
            'chapter_word_count' => 1200,
            'resume_anchor' => ['last_line' => 'The door clicks shut.'],
        ]);

        $this->actingAs($user)->post(route('stories.saves.reset', [$story, $save]));

        $fresh = $save->fresh();
        $this->assertSame(0, $fresh->beat_word_count);
        $this->assertNull($fresh->resume_anchor);
    }

    public function test_resetting_one_save_does_not_touch_a_sibling(): void
    {
        $user = User::factory()->create();
        [$story, $save] = $this->storyWithSave($user);
        $sibling = $story->playSessions()->create([
            'name' => 'Sibling',
            'state_node' => StateNode::NarratorTurn,
            'beat_word_count' => 320,
        ]);

        $this->actingAs($user)->post(route('stories.saves.reset', [$story, $save]));

        $this->assertSame(320, $sibling->fresh()->beat_word_count);
    }

    public function test_resetting_a_save_does_not_mutate_the_authoring_template(): void
    {
        $user = User::factory()->create();
        [$story, $save, $beat] = $this->storyWithSave($user);

        $this->actingAs($user)->post(route('stories.saves.reset', [$story, $save]));

        $this->assertDatabaseHas('beats', ['id' => $beat->id, 'goal' => $beat->goal]);
        $this->assertDatabaseCount('beats', 1);
    }

    public function test_owner_can_delete_a_save(): void
    {
        $user = User::factory()->create();
        [$story, $save] = $this->storyWithSave($user);

        $this->actingAs($user)->delete(route('stories.saves.destroy', [$story, $save]));

        $this->assertDatabaseMissing('play_sessions', ['id' => $save->id]);
    }

    public function test_deleting_one_save_leaves_a_sibling_untouched(): void
    {
        $user = User::factory()->create();
        [$story, $save] = $this->storyWithSave($user);
        $sibling = $story->playSessions()->create([
            'name' => 'Sibling',
            'state_node' => StateNode::SessionStart,
        ]);

        $this->actingAs($user)->delete(route('stories.saves.destroy', [$story, $save]));

        $this->assertDatabaseHas('play_sessions', ['id' => $sibling->id]);
    }

    public function test_deleting_a_save_does_not_mutate_the_authoring_template(): void
    {
        $user = User::factory()->create();
        [$story, $save, $beat] = $this->storyWithSave($user);

        $this->actingAs($user)->delete(route('stories.saves.destroy', [$story, $save]));

        $this->assertDatabaseHas('beats', ['id' => $beat->id]);
        $this->assertDatabaseCount('beats', 1);
    }

    public function test_opening_a_save_stamps_last_played_at(): void
    {
        $this->freezeTime();
        $user = User::factory()->create();
        [$story, $save] = $this->storyWithSave($user);
        $save->update(['last_played_at' => now()->subDay()]);

        $this->actingAs($user)->get(route('stories.saves.play', [$story, $save]));

        // The DB timestamp truncates microseconds, so compare at second precision.
        $this->assertSame(now()->toDateTimeString(), $save->fresh()->last_played_at->toDateTimeString());
    }

    public function test_resume_restores_the_exact_persisted_position(): void
    {
        $user = User::factory()->create();
        $story = Story::factory()->create(['user_id' => $user->id]);
        $this->seedGlobalModelRoles();
        Character::factory()->create(['story_id' => $story->id]);
        $chapter = Chapter::factory()->create(['story_id' => $story->id, 'number' => 1]);
        $scene = Scene::factory()->create(['chapter_id' => $chapter->id, 'number' => 1]);
        Beat::factory()->create(['scene_id' => $scene->id, 'number' => 1]);
        // A second beat the save is parked on mid-play — resume must land here,
        // not back at the first beat.
        $secondBeat = Beat::factory()->create(['scene_id' => $scene->id, 'number' => 2, 'goal' => 'Confront the archivist']);

        $save = $story->playSessions()->create([
            'name' => 'Mid-run',
            'state_node' => StateNode::NarratorTurn,
            'current_chapter_id' => $chapter->id,
            'current_scene_id' => $scene->id,
            'current_beat_id' => $secondBeat->id,
            'resume_anchor' => ['last_line' => 'She turned the page.'],
        ]);

        $response = $this->actingAs($user)->get(route('stories.saves.play', [$story, $save]));

        $response->assertInertia(fn ($page) => $page
            ->component('sessions/Play')
            ->where('save.stateNode', StateNode::NarratorTurn->value)
            ->where('save.position.beatGoal', 'Confront the archivist')
            ->where('save.resumeAnchor.last_line', 'She turned the page.')
        );
    }

    public function test_saves_are_listed_most_recently_played_first(): void
    {
        $user = User::factory()->create();
        [$story, $older] = $this->storyWithSave($user);
        $older->update(['name' => 'Older', 'last_played_at' => now()->subWeek()]);
        $newer = $story->playSessions()->create([
            'name' => 'Newer',
            'state_node' => StateNode::SessionStart,
            'last_played_at' => now(),
        ]);

        $response = $this->actingAs($user)->get(route('stories.saves.index', $story));

        $response->assertInertia(fn ($page) => $page->where('saves.0.id', $newer->id));
    }

    public function test_a_save_from_another_story_cannot_be_renamed(): void
    {
        $user = User::factory()->create();
        [, $save] = $this->storyWithSave($user);
        $otherStory = Story::factory()->create(['user_id' => $user->id, 'slug' => 'other-story']);

        $response = $this->actingAs($user)->put(
            route('stories.saves.update', ['story' => $otherStory->slug, 'playSession' => $save->id]),
            ['name' => 'Hijacked'],
        );

        $response->assertNotFound();
    }

    public function test_a_foreign_story_save_cannot_be_deleted(): void
    {
        $owner = User::factory()->create();
        $intruder = User::factory()->create();
        [$story, $save] = $this->storyWithSave($owner);

        $response = $this->actingAs($intruder)->delete(route('stories.saves.destroy', [$story, $save]));

        $response->assertNotFound();
        $this->assertDatabaseHas('play_sessions', ['id' => $save->id]);
    }

    public function test_guests_cannot_reset_a_save(): void
    {
        $user = User::factory()->create();
        [$story, $save] = $this->storyWithSave($user);

        $response = $this->post(route('stories.saves.reset', [$story, $save]));

        $response->assertRedirect(route('login'));
    }

    /**
     * Build a play-ready story for the owner and return it with its first beat.
     *
     * @param  User  $user  The owner.
     * @return array{0: Story, 1: Beat}
     */
    private function playReadyStory(User $user): array
    {
        $story = Story::factory()->create(['user_id' => $user->id]);
        $this->seedGlobalModelRoles();
        Character::factory()->create(['story_id' => $story->id]);
        $chapter = Chapter::factory()->create(['story_id' => $story->id, 'number' => 1]);
        $scene = Scene::factory()->create(['chapter_id' => $chapter->id, 'number' => 1]);
        $beat = Beat::factory()->create(['scene_id' => $scene->id, 'number' => 1]);

        return [$story, $beat->load('scene')];
    }

    /**
     * Build a play-ready story with one forked save and return story + save + beat.
     *
     * @param  User  $user  The owner.
     * @return array{0: Story, 1: PlaySession, 2: Beat}
     */
    private function storyWithSave(User $user): array
    {
        [$story, $beat] = $this->playReadyStory($user);

        $save = $story->playSessions()->create([
            'name' => 'Playthrough 1',
            'state_node' => StateNode::SessionStart,
            'current_chapter_id' => $beat->scene->chapter_id,
            'current_scene_id' => $beat->scene_id,
            'current_beat_id' => $beat->id,
            'last_played_at' => now(),
        ]);

        return [$story, $save, $beat];
    }

    /**
     * Seed a global model profile for every engine role so resolution succeeds.
     */
    private function seedGlobalModelRoles(): void
    {
        foreach (LlmRole::cases() as $role) {
            ModelProfile::factory()->create([
                'scope' => ModelScope::Global,
                'story_id' => null,
                'role' => $role,
            ]);
        }
    }
}
