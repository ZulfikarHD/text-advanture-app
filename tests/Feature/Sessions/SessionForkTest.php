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
use App\Services\SessionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

/**
 * Feature tests for starting a session — the fork (S-2.1.1).
 *
 * Covers: a play-ready story forks into one save at session_start positioned at
 * the first beat (in document order); the fork seeds no relationship edges and
 * never mutates the authoring template; the fork is atomic (a mid-fork failure
 * leaves no row); a not-play-ready story is rejected; owner-scoping (foreign
 * story / cross-story save both 404); the auth gate; and the Saves list + Play
 * surface render the fork's result.
 */
class SessionForkTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_start_a_session_and_lands_on_the_play_surface(): void
    {
        $user = User::factory()->create();
        [$story] = $this->playReadyStory($user);

        $response = $this->actingAs($user)->post(route('stories.saves.store', $story));

        $session = $story->playSessions()->firstOrFail();
        $response->assertRedirect(route('stories.saves.play', [$story, $session]));
    }

    public function test_fork_creates_one_save_at_session_start(): void
    {
        $user = User::factory()->create();
        [$story] = $this->playReadyStory($user);

        $this->actingAs($user)->post(route('stories.saves.store', $story));

        $this->assertDatabaseCount('play_sessions', 1);
        $this->assertSame(StateNode::SessionStart, $story->playSessions()->firstOrFail()->state_node);
    }

    public function test_fork_positions_the_save_at_the_first_beat(): void
    {
        $user = User::factory()->create();
        [$story, $beat] = $this->playReadyStory($user);

        $this->actingAs($user)->post(route('stories.saves.store', $story));

        $session = $story->playSessions()->firstOrFail();
        $this->assertSame($beat->id, $session->current_beat_id);
        $this->assertSame($beat->scene_id, $session->current_scene_id);
        $this->assertSame($beat->scene->chapter_id, $session->current_chapter_id);
    }

    public function test_fork_positions_at_the_first_beat_in_document_order(): void
    {
        $user = User::factory()->create();
        $story = Story::factory()->create(['user_id' => $user->id]);
        $this->seedGlobalModelRoles();
        Character::factory()->create(['story_id' => $story->id]);

        // Chapter 1 has a scene but no beat; the first playable beat lives in
        // chapter 2, so the save must position there — not at chapter 1.
        $chapterOne = Chapter::factory()->create(['story_id' => $story->id, 'number' => 1]);
        Scene::factory()->create(['chapter_id' => $chapterOne->id, 'number' => 1]);
        $chapterTwo = Chapter::factory()->create(['story_id' => $story->id, 'number' => 2]);
        $sceneTwo = Scene::factory()->create(['chapter_id' => $chapterTwo->id, 'number' => 1]);
        $firstBeat = Beat::factory()->create(['scene_id' => $sceneTwo->id, 'number' => 1]);

        $this->actingAs($user)->post(route('stories.saves.store', $story));

        $session = $story->playSessions()->firstOrFail();
        $this->assertSame($chapterTwo->id, $session->current_chapter_id);
        $this->assertSame($firstBeat->id, $session->current_beat_id);
    }

    public function test_fork_seeds_no_relationship_edges(): void
    {
        $user = User::factory()->create();
        [$story] = $this->playReadyStory($user);

        $this->actingAs($user)->post(route('stories.saves.store', $story));

        $this->assertDatabaseCount('relationship_edges', 0);
    }

    public function test_fork_does_not_mutate_the_authoring_template(): void
    {
        $user = User::factory()->create();
        [$story, $beat] = $this->playReadyStory($user);

        $this->actingAs($user)->post(route('stories.saves.store', $story));

        // No authoring row is added or changed: the save references the template.
        $this->assertDatabaseHas('beats', ['id' => $beat->id, 'goal' => $beat->goal]);
        $this->assertDatabaseCount('beats', 1);
        $this->assertDatabaseCount('scenes', 1);
        $this->assertDatabaseCount('chapters', 1);
        $this->assertDatabaseCount('characters', 1);
    }

    public function test_a_not_play_ready_story_is_not_forked(): void
    {
        $user = User::factory()->create();
        // A bare story: no characters, structure, or resolvable model roles.
        $story = Story::factory()->create(['user_id' => $user->id]);

        $response = $this->actingAs($user)->post(route('stories.saves.store', $story));

        $response->assertRedirect(route('stories.saves.index', $story));
        $this->assertDatabaseCount('play_sessions', 0);
    }

    public function test_fork_is_atomic_when_a_step_fails(): void
    {
        $user = User::factory()->create();
        [$story] = $this->playReadyStory($user);

        // Simulate a failure partway through the fork, after the row is inserted
        // but still inside the transaction: it must roll back, leaving no save.
        PlaySession::created(function (): void {
            throw new RuntimeException('Simulated mid-fork failure.');
        });

        try {
            app(SessionService::class)->fork($story);
            $this->fail('The simulated mid-fork failure should have propagated.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Simulated mid-fork failure.', $exception->getMessage());
        }

        $this->assertDatabaseCount('play_sessions', 0);
    }

    public function test_a_foreign_story_cannot_be_forked(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $story = Story::factory()->create(['user_id' => $other->id, 'slug' => 'theirs']);
        $this->seedGlobalModelRoles();
        Character::factory()->create(['story_id' => $story->id]);
        $chapter = Chapter::factory()->create(['story_id' => $story->id, 'number' => 1]);
        $scene = Scene::factory()->create(['chapter_id' => $chapter->id, 'number' => 1]);
        Beat::factory()->create(['scene_id' => $scene->id, 'number' => 1]);

        $response = $this->actingAs($owner)->post(route('stories.saves.store', ['story' => 'theirs']));

        $response->assertNotFound();
        $this->assertDatabaseCount('play_sessions', 0);
    }

    public function test_guests_cannot_start_a_session(): void
    {
        $story = Story::factory()->create();

        $response = $this->post(route('stories.saves.store', $story));

        $response->assertRedirect(route('login'));
        $this->assertDatabaseCount('play_sessions', 0);
    }

    public function test_saves_index_lists_the_forked_saves(): void
    {
        $user = User::factory()->create();
        [$story] = $this->playReadyStory($user);
        $this->actingAs($user)->post(route('stories.saves.store', $story));
        $session = $story->playSessions()->firstOrFail();

        $response = $this->actingAs($user)->get(route('stories.saves.index', $story));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('stories/Saves')
            ->where('readiness.ready', true)
            ->where('saves.0.id', $session->id)
            ->where('saves.0.stateNode', StateNode::SessionStart->value)
        );
    }

    public function test_owner_can_open_the_play_surface_for_a_save(): void
    {
        $user = User::factory()->create();
        [$story] = $this->playReadyStory($user);
        $this->actingAs($user)->post(route('stories.saves.store', $story));
        $session = $story->playSessions()->firstOrFail();

        $response = $this->actingAs($user)->get(route('stories.saves.play', [$story, $session]));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('sessions/Play')
            ->where('save.id', $session->id)
            ->where('save.name', $session->name)
        );
    }

    public function test_play_surface_404s_for_a_save_from_another_story(): void
    {
        $user = User::factory()->create();
        [$story] = $this->playReadyStory($user);
        $this->actingAs($user)->post(route('stories.saves.store', $story));
        $session = $story->playSessions()->firstOrFail();

        // A second story the owner also holds — the save belongs to the first,
        // so the scoped binding must reject it under the second.
        $otherStory = Story::factory()->create(['user_id' => $user->id, 'slug' => 'other-story']);

        $response = $this->actingAs($user)->get(
            route('stories.saves.play', ['story' => $otherStory->slug, 'playSession' => $session->id]),
        );

        $response->assertNotFound();
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
