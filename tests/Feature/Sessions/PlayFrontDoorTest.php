<?php

namespace Tests\Feature\Sessions;

use App\Enums\LlmRole;
use App\Enums\ModelScope;
use App\Enums\StateNode;
use App\Models\Beat;
use App\Models\Chapter;
use App\Models\Character;
use App\Models\ModelProfile;
use App\Models\Scene;
use App\Models\Story;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Feature tests for the chapter-first play front door (E0.2 / E0.2.2).
 *
 * Covers the invisible fork: entering a book resumes the most-recent playthrough
 * or silently forks one; entering a chapter positions a fresh playthrough at that
 * chapter without ever rewinding an in-progress save; a not-play-ready book lands
 * back on its overview with an error rather than a dead Writing page; and both
 * entries are owner-scoped + auth gated.
 */
class PlayFrontDoorTest extends TestCase
{
    use RefreshDatabase;

    public function test_entering_a_book_with_no_save_forks_one_and_opens_play(): void
    {
        $user = User::factory()->create();
        [$story] = $this->playReadyStory($user);

        $response = $this->actingAs($user)->get(route('stories.play', $story));

        $session = $story->playSessions()->firstOrFail();
        $this->assertDatabaseCount('play_sessions', 1);
        $response->assertRedirect(route('stories.saves.play', [$story, $session]));
    }

    public function test_entering_a_book_resumes_the_latest_save_without_forking_again(): void
    {
        $user = User::factory()->create();
        [$story] = $this->playReadyStory($user);
        $existing = $this->actingAs($user)->get(route('stories.play', $story));
        $session = $story->playSessions()->firstOrFail();
        $existing->assertRedirect(route('stories.saves.play', [$story, $session]));

        $response = $this->actingAs($user)->get(route('stories.play', $story));

        $this->assertDatabaseCount('play_sessions', 1);
        $response->assertRedirect(route('stories.saves.play', [$story, $session]));
    }

    public function test_entering_a_chapter_positions_a_fresh_save_at_that_chapter(): void
    {
        $user = User::factory()->create();
        [$story, , $chapterTwo, $beatTwo] = $this->twoChapterStory($user);

        $this->actingAs($user)->get(route('stories.chapters.play', [$story, $chapterTwo]));

        $session = $story->playSessions()->firstOrFail();
        $this->assertSame($chapterTwo->id, $session->current_chapter_id);
        $this->assertSame($beatTwo->id, $session->current_beat_id);
    }

    public function test_entering_a_chapter_does_not_rewind_an_in_progress_save(): void
    {
        $user = User::factory()->create();
        [$story, $chapterOne, $chapterTwo, $beatTwo] = $this->twoChapterStory($user);

        // An in-progress playthrough already at chapter two.
        $save = $story->playSessions()->create([
            'name' => 'Playthrough 1',
            'state_node' => StateNode::NarratorTurn,
            'current_chapter_id' => $chapterTwo->id,
            'current_scene_id' => $beatTwo->scene_id,
            'current_beat_id' => $beatTwo->id,
            'last_played_at' => now(),
        ]);

        $this->actingAs($user)->get(route('stories.chapters.play', [$story, $chapterOne]));

        $this->assertDatabaseCount('play_sessions', 1);
        $this->assertSame($chapterTwo->id, $save->fresh()->current_chapter_id);
    }

    public function test_entering_a_not_play_ready_book_redirects_to_the_overview(): void
    {
        $user = User::factory()->create();
        $story = Story::factory()->create(['user_id' => $user->id]);

        $response = $this->actingAs($user)->get(route('stories.play', $story));

        $response->assertRedirect(route('stories.show', $story));
        $this->assertDatabaseCount('play_sessions', 0);
    }

    public function test_a_not_play_ready_book_surfaces_an_error_toast(): void
    {
        $user = User::factory()->create();
        $story = Story::factory()->create(['user_id' => $user->id]);

        $response = $this->actingAs($user)->get(route('stories.play', $story));

        $response->assertInertiaFlash('toast.type', 'error');
    }

    public function test_guests_cannot_enter_a_book(): void
    {
        $story = Story::factory()->create();

        $response = $this->get(route('stories.play', $story));

        $response->assertRedirect(route('login'));
    }

    public function test_a_foreign_book_cannot_be_entered(): void
    {
        $owner = User::factory()->create();
        $intruder = User::factory()->create();
        [$story] = $this->playReadyStory($owner);

        $response = $this->actingAs($intruder)->get(route('stories.play', $story));

        $response->assertNotFound();
        $this->assertDatabaseCount('play_sessions', 0);
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
     * Build a play-ready two-chapter story for chapter-entry assertions.
     *
     * @param  User  $user  The owner.
     * @return array{0: Story, 1: Chapter, 2: Chapter, 3: Beat}
     */
    private function twoChapterStory(User $user): array
    {
        $story = Story::factory()->create(['user_id' => $user->id]);
        $this->seedGlobalModelRoles();
        Character::factory()->create(['story_id' => $story->id]);

        $chapterOne = Chapter::factory()->create(['story_id' => $story->id, 'number' => 1]);
        $sceneOne = Scene::factory()->create(['chapter_id' => $chapterOne->id, 'number' => 1]);
        Beat::factory()->create(['scene_id' => $sceneOne->id, 'number' => 1]);

        $chapterTwo = Chapter::factory()->create(['story_id' => $story->id, 'number' => 2]);
        $sceneTwo = Scene::factory()->create(['chapter_id' => $chapterTwo->id, 'number' => 1]);
        $beatTwo = Beat::factory()->create(['scene_id' => $sceneTwo->id, 'number' => 1]);

        return [$story, $chapterOne, $chapterTwo, $beatTwo->load('scene')];
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
