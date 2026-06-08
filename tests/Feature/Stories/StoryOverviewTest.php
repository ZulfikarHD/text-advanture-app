<?php

namespace Tests\Feature\Stories;

use App\Enums\LlmRole;
use App\Enums\ModelScope;
use App\Models\Beat;
use App\Models\Chapter;
use App\Models\Character;
use App\Models\LorebookEntry;
use App\Models\ModelProfile;
use App\Models\PlaySession;
use App\Models\RevealLedger;
use App\Models\Scene;
use App\Models\Story;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Feature tests for the story overview (S-1.2.2).
 *
 * Covers: derived authoring counts, the play-readiness gate (ready vs. an
 * enumerated list of what is missing), and owner-scoped 404s.
 */
class StoryOverviewTest extends TestCase
{
    use RefreshDatabase;

    public function test_overview_reports_derived_counts(): void
    {
        $user = User::factory()->create();
        $story = Story::factory()->create(['user_id' => $user->id]);

        Character::factory()->count(2)->create(['story_id' => $story->id]);
        $chapter = Chapter::factory()->create(['story_id' => $story->id, 'number' => 1]);
        $scene = Scene::factory()->create(['chapter_id' => $chapter->id, 'number' => 1]);
        Beat::factory()->create(['scene_id' => $scene->id, 'number' => 1]);
        LorebookEntry::factory()->create(['story_id' => $story->id]);
        RevealLedger::factory()->create(['story_id' => $story->id]);
        PlaySession::factory()->create(['story_id' => $story->id]);

        $response = $this->actingAs($user)->get(route('stories.show', $story));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('stories/Overview')
            ->where('counts.characters', 2)
            ->where('counts.chapters', 1)
            ->where('counts.scenes', 1)
            ->where('counts.beats', 1)
            ->where('counts.lorebookEntries', 1)
            ->where('counts.revealLedgerEntries', 1)
            ->where('counts.saves', 1)
        );
    }

    public function test_a_complete_story_is_reported_play_ready(): void
    {
        $user = User::factory()->create();
        $story = Story::factory()->create(['user_id' => $user->id]);

        $this->seedGlobalModelRoles();
        Character::factory()->create(['story_id' => $story->id]);
        $chapter = Chapter::factory()->create(['story_id' => $story->id, 'number' => 1]);
        $scene = Scene::factory()->create(['chapter_id' => $chapter->id, 'number' => 1]);
        Beat::factory()->create(['scene_id' => $scene->id, 'number' => 1]);

        $response = $this->actingAs($user)->get(route('stories.show', $story));

        $response->assertInertia(fn ($page) => $page
            ->where('readiness.ready', true)
        );
    }

    public function test_a_story_without_characters_is_not_play_ready(): void
    {
        $user = User::factory()->create();
        $story = Story::factory()->create(['user_id' => $user->id]);

        $this->seedGlobalModelRoles();
        $chapter = Chapter::factory()->create(['story_id' => $story->id, 'number' => 1]);
        $scene = Scene::factory()->create(['chapter_id' => $chapter->id, 'number' => 1]);
        Beat::factory()->create(['scene_id' => $scene->id, 'number' => 1]);

        $response = $this->actingAs($user)->get(route('stories.show', $story));

        $response->assertInertia(fn ($page) => $page
            ->where('readiness.ready', false)
            ->where('readiness.requirements.0.key', 'characters')
            ->where('readiness.requirements.0.met', false)
        );
    }

    public function test_a_story_with_a_scene_but_no_beat_is_not_play_ready(): void
    {
        $user = User::factory()->create();
        $story = Story::factory()->create(['user_id' => $user->id]);

        // Everything but the structure requirement is satisfied, so the unmet
        // "chapter with a scene and a beat" gate is isolated: a chapter and a
        // scene exist, but no beat means there is nothing for the engine to direct.
        $this->seedGlobalModelRoles();
        Character::factory()->create(['story_id' => $story->id]);
        $chapter = Chapter::factory()->create(['story_id' => $story->id, 'number' => 1]);
        Scene::factory()->create(['chapter_id' => $chapter->id, 'number' => 1]);

        $response = $this->actingAs($user)->get(route('stories.show', $story));

        $response->assertInertia(fn ($page) => $page
            ->where('readiness.ready', false)
            ->where('readiness.requirements.1.key', 'structure')
            ->where('readiness.requirements.1.met', false)
        );
    }

    public function test_a_story_with_no_resolvable_model_is_not_play_ready(): void
    {
        $user = User::factory()->create();
        $story = Story::factory()->create(['user_id' => $user->id]);

        Character::factory()->create(['story_id' => $story->id]);
        $chapter = Chapter::factory()->create(['story_id' => $story->id, 'number' => 1]);
        $scene = Scene::factory()->create(['chapter_id' => $chapter->id, 'number' => 1]);
        Beat::factory()->create(['scene_id' => $scene->id, 'number' => 1]);

        $response = $this->actingAs($user)->get(route('stories.show', $story));

        $response->assertInertia(fn ($page) => $page
            ->where('readiness.ready', false)
            ->where('readiness.requirements.2.key', 'model_config')
            ->where('readiness.requirements.2.met', false)
        );
    }

    public function test_overview_exposes_the_chapter_spine_with_beat_counts(): void
    {
        $user = User::factory()->create();
        $story = Story::factory()->create(['user_id' => $user->id]);

        $chapter = Chapter::factory()->create(['story_id' => $story->id, 'number' => 1, 'title' => 'The Arrival']);
        $scene = Scene::factory()->create(['chapter_id' => $chapter->id, 'number' => 1]);
        Beat::factory()->count(2)->sequence(
            ['number' => 1],
            ['number' => 2],
        )->create(['scene_id' => $scene->id]);

        $response = $this->actingAs($user)->get(route('stories.show', $story));

        $response->assertInertia(fn ($page) => $page
            ->component('stories/Overview')
            ->where('chapters.0.title', 'The Arrival')
            ->where('chapters.0.playableBeats', 2)
        );
    }

    public function test_overview_404s_on_foreign_story(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        Story::factory()->create(['user_id' => $other->id, 'slug' => 'theirs']);

        $response = $this->actingAs($owner)->get(route('stories.show', ['story' => 'theirs']));

        $response->assertNotFound();
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
