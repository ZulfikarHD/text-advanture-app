<?php

namespace Tests\Feature\Stories;

use App\Enums\LlmRole;
use App\Enums\ModelScope;
use App\Models\Beat;
use App\Models\Chapter;
use App\Models\Character;
use App\Models\CharacterCard;
use App\Models\ModelProfile;
use App\Models\Scene;
use App\Models\Story;
use App\Models\User;
use App\Services\StructureService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Feature tests for minimal manual structure CRUD (S-1.2.1).
 *
 * Covers: chapter/scene/beat create-update-delete with system-managed `number`
 * (max + 1 per parent), the mandatory beat goal + its deferred intent/word-budget
 * defaults, the scene POV-contract validation (mode enum, present cast, anchor ∈
 * present ∈ story cast), owner-scoped nested binding (a row from another story
 * 404s), the chapter-delete guard against character cards, no LLM call, the auth
 * gate, and that authoring a chapter → scene → beat makes a story play-ready.
 */
class StructureCrudTest extends TestCase
{
    use RefreshDatabase;

    /**
     * A story owned by the given user plus an NPC (for scene anchoring) and a
     * chapter (for scene/beat parents).
     *
     * @return array{0: Story, 1: Character, 2: Chapter}
     */
    private function storyWithChapter(User $user): array
    {
        $story = Story::factory()->create(['user_id' => $user->id]);
        $character = Character::factory()->create(['story_id' => $story->id]);
        $chapter = Chapter::factory()->create(['story_id' => $story->id, 'number' => 1]);

        return [$story, $character, $chapter];
    }

    /**
     * The minimal scene payload anchored on the given character.
     *
     * @return array<string, mixed>
     */
    private function scenePayload(Character $character, array $overrides = []): array
    {
        return array_merge([
            'pov_mode' => 'third_limited',
            'pov_anchor' => $character->slug,
            'tone' => 'tense',
            'present_characters' => [$character->slug],
        ], $overrides);
    }

    // --- Chapters ---

    public function test_owner_can_create_a_chapter(): void
    {
        $user = User::factory()->create();
        $story = Story::factory()->create(['user_id' => $user->id]);

        $response = $this->actingAs($user)->post(
            route('stories.structure.chapters.store', $story),
            ['title' => 'The Arrival', 'pov_default' => 'third_limited'],
        );

        $response->assertRedirect(route('stories.structure.index', $story));
        $this->assertDatabaseHas('chapters', [
            'story_id' => $story->id,
            'number' => 1,
            'title' => 'The Arrival',
            'pov_default' => 'third_limited',
        ]);
    }

    public function test_chapter_number_auto_increments_per_story(): void
    {
        $user = User::factory()->create();
        $story = Story::factory()->create(['user_id' => $user->id]);

        $this->actingAs($user)->post(route('stories.structure.chapters.store', $story), ['title' => 'One', 'pov_default' => 'third_limited']);
        $this->actingAs($user)->post(route('stories.structure.chapters.store', $story), ['title' => 'Two', 'pov_default' => 'third_limited']);

        $this->assertSame([1, 2], $story->chapters()->orderBy('number')->pluck('number')->all());
    }

    public function test_chapter_requires_a_title(): void
    {
        $user = User::factory()->create();
        $story = Story::factory()->create(['user_id' => $user->id]);

        $response = $this->actingAs($user)->post(
            route('stories.structure.chapters.store', $story),
            ['title' => '', 'pov_default' => 'third_limited'],
        );

        $response->assertSessionHasErrors('title');
        $this->assertDatabaseCount('chapters', 0);
    }

    public function test_chapter_rejects_an_unknown_pov(): void
    {
        $user = User::factory()->create();
        $story = Story::factory()->create(['user_id' => $user->id]);

        $response = $this->actingAs($user)->post(
            route('stories.structure.chapters.store', $story),
            ['title' => 'One', 'pov_default' => 'sideways'],
        );

        $response->assertSessionHasErrors('pov_default');
    }

    public function test_owner_can_update_a_chapter(): void
    {
        $user = User::factory()->create();
        $story = Story::factory()->create(['user_id' => $user->id]);
        $chapter = Chapter::factory()->create(['story_id' => $story->id, 'number' => 1]);

        $response = $this->actingAs($user)->put(
            route('stories.structure.chapters.update', ['story' => $story->slug, 'chapter' => $chapter->id]),
            ['title' => 'Renamed', 'pov_default' => 'first_person'],
        );

        $response->assertRedirect(route('stories.structure.index', $story));
        $this->assertDatabaseHas('chapters', ['id' => $chapter->id, 'title' => 'Renamed', 'pov_default' => 'first_person']);
    }

    public function test_owner_can_delete_a_chapter_without_character_cards(): void
    {
        $user = User::factory()->create();
        $story = Story::factory()->create(['user_id' => $user->id]);
        $chapter = Chapter::factory()->create(['story_id' => $story->id, 'number' => 1]);

        $response = $this->actingAs($user)->delete(
            route('stories.structure.chapters.destroy', ['story' => $story->slug, 'chapter' => $chapter->id]),
        );

        $response->assertRedirect(route('stories.structure.index', $story));
        $this->assertDatabaseMissing('chapters', ['id' => $chapter->id]);
    }

    public function test_deleting_a_chapter_with_character_cards_is_rejected(): void
    {
        $user = User::factory()->create();
        $story = Story::factory()->create(['user_id' => $user->id]);
        $chapter = Chapter::factory()->create(['story_id' => $story->id, 'number' => 1]);
        $character = Character::factory()->create(['story_id' => $story->id]);
        CharacterCard::factory()->create([
            'character_id' => $character->id,
            'chapter_id' => $chapter->id,
        ]);

        $response = $this->actingAs($user)->delete(
            route('stories.structure.chapters.destroy', ['story' => $story->slug, 'chapter' => $chapter->id]),
        );

        $response->assertRedirect(route('stories.structure.index', $story));
        // Guard holds: the chapter (and the cast's cards) survive.
        $this->assertDatabaseHas('chapters', ['id' => $chapter->id]);
    }

    public function test_chapter_update_404s_for_chapter_from_another_story(): void
    {
        $user = User::factory()->create();
        $storyA = Story::factory()->create(['user_id' => $user->id]);
        $storyB = Story::factory()->create(['user_id' => $user->id]);
        $chapterB = Chapter::factory()->create(['story_id' => $storyB->id, 'number' => 1]);

        $response = $this->actingAs($user)->put(
            route('stories.structure.chapters.update', ['story' => $storyA->slug, 'chapter' => $chapterB->id]),
            ['title' => 'Hijack', 'pov_default' => 'third_limited'],
        );

        $response->assertNotFound();
    }

    // --- Scenes ---

    public function test_owner_can_create_a_scene(): void
    {
        $user = User::factory()->create();
        [$story, $character, $chapter] = $this->storyWithChapter($user);

        $response = $this->actingAs($user)->post(
            route('stories.structure.scenes.store', ['story' => $story->slug, 'chapter' => $chapter->id]),
            $this->scenePayload($character),
        );

        $response->assertRedirect(route('stories.structure.index', $story));
        $this->assertDatabaseHas('scenes', [
            'chapter_id' => $chapter->id,
            'number' => 1,
            'pov_mode' => 'third_limited',
            'pov_anchor' => $character->slug,
            'tone' => 'tense',
        ]);
    }

    public function test_created_scene_records_default_elapsed_source(): void
    {
        $user = User::factory()->create();
        [$story, $character, $chapter] = $this->storyWithChapter($user);

        $this->actingAs($user)->post(
            route('stories.structure.scenes.store', ['story' => $story->slug, 'chapter' => $chapter->id]),
            $this->scenePayload($character),
        );

        $scene = Scene::firstOrFail();
        $this->assertSame('default', $scene->elapsed_source->value);
        $this->assertSame([$character->slug], $scene->present_characters);
    }

    public function test_scene_number_auto_increments_per_chapter(): void
    {
        $user = User::factory()->create();
        [$story, $character, $chapter] = $this->storyWithChapter($user);

        $this->actingAs($user)->post(route('stories.structure.scenes.store', ['story' => $story->slug, 'chapter' => $chapter->id]), $this->scenePayload($character));
        $this->actingAs($user)->post(route('stories.structure.scenes.store', ['story' => $story->slug, 'chapter' => $chapter->id]), $this->scenePayload($character));

        $this->assertSame([1, 2], $chapter->scenes()->orderBy('number')->pluck('number')->all());
    }

    public function test_scene_requires_at_least_one_present_character(): void
    {
        $user = User::factory()->create();
        [$story, $character, $chapter] = $this->storyWithChapter($user);

        $response = $this->actingAs($user)->post(
            route('stories.structure.scenes.store', ['story' => $story->slug, 'chapter' => $chapter->id]),
            $this->scenePayload($character, ['present_characters' => []]),
        );

        $response->assertSessionHasErrors('present_characters');
        $this->assertDatabaseCount('scenes', 0);
    }

    public function test_scene_pov_anchor_must_be_present(): void
    {
        $user = User::factory()->create();
        [$story, $character, $chapter] = $this->storyWithChapter($user);
        $other = Character::factory()->create(['story_id' => $story->id]);

        $response = $this->actingAs($user)->post(
            route('stories.structure.scenes.store', ['story' => $story->slug, 'chapter' => $chapter->id]),
            // Anchor is a story character but not in the present cast.
            $this->scenePayload($character, [
                'pov_anchor' => $other->slug,
                'present_characters' => [$character->slug],
            ]),
        );

        $response->assertSessionHasErrors('pov_anchor');
    }

    public function test_scene_present_characters_must_belong_to_the_story(): void
    {
        $user = User::factory()->create();
        [$story, $character, $chapter] = $this->storyWithChapter($user);

        $response = $this->actingAs($user)->post(
            route('stories.structure.scenes.store', ['story' => $story->slug, 'chapter' => $chapter->id]),
            $this->scenePayload($character, [
                'pov_anchor' => 'stranger',
                'present_characters' => ['stranger'],
            ]),
        );

        $response->assertSessionHasErrors('present_characters');
    }

    public function test_scene_rejects_an_unknown_pov_mode(): void
    {
        $user = User::factory()->create();
        [$story, $character, $chapter] = $this->storyWithChapter($user);

        $response = $this->actingAs($user)->post(
            route('stories.structure.scenes.store', ['story' => $story->slug, 'chapter' => $chapter->id]),
            $this->scenePayload($character, ['pov_mode' => 'sideways']),
        );

        $response->assertSessionHasErrors('pov_mode');
    }

    public function test_owner_can_update_a_scene(): void
    {
        $user = User::factory()->create();
        [$story, $character, $chapter] = $this->storyWithChapter($user);
        $scene = Scene::factory()->create(['chapter_id' => $chapter->id, 'number' => 1]);

        $response = $this->actingAs($user)->put(
            route('stories.structure.scenes.update', ['story' => $story->slug, 'chapter' => $chapter->id, 'scene' => $scene->id]),
            $this->scenePayload($character, ['tone' => 'calm']),
        );

        $response->assertRedirect(route('stories.structure.index', $story));
        $this->assertDatabaseHas('scenes', ['id' => $scene->id, 'tone' => 'calm', 'pov_anchor' => $character->slug]);
    }

    public function test_owner_can_delete_a_scene(): void
    {
        $user = User::factory()->create();
        [$story, , $chapter] = $this->storyWithChapter($user);
        $scene = Scene::factory()->create(['chapter_id' => $chapter->id, 'number' => 1]);

        $response = $this->actingAs($user)->delete(
            route('stories.structure.scenes.destroy', ['story' => $story->slug, 'chapter' => $chapter->id, 'scene' => $scene->id]),
        );

        $response->assertRedirect(route('stories.structure.index', $story));
        $this->assertDatabaseMissing('scenes', ['id' => $scene->id]);
    }

    public function test_scene_store_404s_for_chapter_from_another_story(): void
    {
        $user = User::factory()->create();
        [$storyA, $character] = $this->storyWithChapter($user);
        $storyB = Story::factory()->create(['user_id' => $user->id]);
        $chapterB = Chapter::factory()->create(['story_id' => $storyB->id, 'number' => 1]);

        $response = $this->actingAs($user)->post(
            // chapterB belongs to storyB, but the URL scopes it under storyA.
            route('stories.structure.scenes.store', ['story' => $storyA->slug, 'chapter' => $chapterB->id]),
            $this->scenePayload($character),
        );

        $response->assertNotFound();
    }

    // --- Beats ---

    public function test_owner_can_create_a_beat(): void
    {
        $user = User::factory()->create();
        [$story, , $chapter] = $this->storyWithChapter($user);
        $scene = Scene::factory()->create(['chapter_id' => $chapter->id, 'number' => 1]);

        $response = $this->actingAs($user)->post(
            route('stories.structure.beats.store', ['story' => $story->slug, 'chapter' => $chapter->id, 'scene' => $scene->id]),
            ['goal' => 'Luna and the player meet'],
        );

        $response->assertRedirect(route('stories.structure.index', $story));
        $this->assertDatabaseHas('beats', [
            'scene_id' => $scene->id,
            'number' => 1,
            'goal' => 'Luna and the player meet',
        ]);
    }

    public function test_created_beat_stores_deferred_intent_and_word_budget_defaults(): void
    {
        $user = User::factory()->create();
        [$story, , $chapter] = $this->storyWithChapter($user);
        $scene = Scene::factory()->create(['chapter_id' => $chapter->id, 'number' => 1]);

        $this->actingAs($user)->post(
            route('stories.structure.beats.store', ['story' => $story->slug, 'chapter' => $chapter->id, 'scene' => $scene->id]),
            ['goal' => 'They meet'],
        );

        $beat = Beat::firstOrFail();
        $this->assertSame('', $beat->intent);
        $this->assertSame(StructureService::DEFAULT_WORD_BUDGET, $beat->word_budget);
    }

    public function test_beat_requires_a_goal(): void
    {
        $user = User::factory()->create();
        [$story, , $chapter] = $this->storyWithChapter($user);
        $scene = Scene::factory()->create(['chapter_id' => $chapter->id, 'number' => 1]);

        $response = $this->actingAs($user)->post(
            route('stories.structure.beats.store', ['story' => $story->slug, 'chapter' => $chapter->id, 'scene' => $scene->id]),
            ['goal' => ''],
        );

        $response->assertSessionHasErrors('goal');
        $this->assertDatabaseCount('beats', 0);
    }

    public function test_beat_number_auto_increments_per_scene(): void
    {
        $user = User::factory()->create();
        [$story, , $chapter] = $this->storyWithChapter($user);
        $scene = Scene::factory()->create(['chapter_id' => $chapter->id, 'number' => 1]);

        $this->actingAs($user)->post(route('stories.structure.beats.store', ['story' => $story->slug, 'chapter' => $chapter->id, 'scene' => $scene->id]), ['goal' => 'First']);
        $this->actingAs($user)->post(route('stories.structure.beats.store', ['story' => $story->slug, 'chapter' => $chapter->id, 'scene' => $scene->id]), ['goal' => 'Second']);

        $this->assertSame([1, 2], $scene->beats()->orderBy('number')->pluck('number')->all());
    }

    public function test_owner_can_update_a_beat(): void
    {
        $user = User::factory()->create();
        [$story, , $chapter] = $this->storyWithChapter($user);
        $scene = Scene::factory()->create(['chapter_id' => $chapter->id, 'number' => 1]);
        $beat = Beat::factory()->create(['scene_id' => $scene->id, 'number' => 1]);

        $response = $this->actingAs($user)->put(
            route('stories.structure.beats.update', ['story' => $story->slug, 'chapter' => $chapter->id, 'scene' => $scene->id, 'beat' => $beat->id]),
            ['goal' => 'A sharper goal'],
        );

        $response->assertRedirect(route('stories.structure.index', $story));
        $this->assertDatabaseHas('beats', ['id' => $beat->id, 'goal' => 'A sharper goal']);
    }

    public function test_owner_can_delete_a_beat(): void
    {
        $user = User::factory()->create();
        [$story, , $chapter] = $this->storyWithChapter($user);
        $scene = Scene::factory()->create(['chapter_id' => $chapter->id, 'number' => 1]);
        $beat = Beat::factory()->create(['scene_id' => $scene->id, 'number' => 1]);

        $response = $this->actingAs($user)->delete(
            route('stories.structure.beats.destroy', ['story' => $story->slug, 'chapter' => $chapter->id, 'scene' => $scene->id, 'beat' => $beat->id]),
        );

        $response->assertRedirect(route('stories.structure.index', $story));
        $this->assertDatabaseMissing('beats', ['id' => $beat->id]);
    }

    public function test_beat_store_404s_for_scene_from_another_story(): void
    {
        $user = User::factory()->create();
        [$storyA, , $chapterA] = $this->storyWithChapter($user);
        $storyB = Story::factory()->create(['user_id' => $user->id]);
        $chapterB = Chapter::factory()->create(['story_id' => $storyB->id, 'number' => 1]);
        $sceneB = Scene::factory()->create(['chapter_id' => $chapterB->id, 'number' => 1]);

        $response = $this->actingAs($user)->post(
            // sceneB nests under storyB, but the URL scopes it under storyA/chapterA.
            route('stories.structure.beats.store', ['story' => $storyA->slug, 'chapter' => $chapterA->id, 'scene' => $sceneB->id]),
            ['goal' => 'Hijack'],
        );

        $response->assertNotFound();
    }

    // --- Cross-cutting ---

    public function test_authoring_structure_makes_no_llm_call(): void
    {
        $user = User::factory()->create();
        [$story, $character, $chapter] = $this->storyWithChapter($user);

        $this->actingAs($user)->post(route('stories.structure.scenes.store', ['story' => $story->slug, 'chapter' => $chapter->id]), $this->scenePayload($character));
        $scene = Scene::firstOrFail();
        $this->actingAs($user)->post(route('stories.structure.beats.store', ['story' => $story->slug, 'chapter' => $chapter->id, 'scene' => $scene->id]), ['goal' => 'They meet']);

        // The manual slice records no LLM call (no API key required).
        $this->assertDatabaseEmpty('llm_calls');
    }

    public function test_index_renders_the_structure_surface_scoped_to_the_story(): void
    {
        $user = User::factory()->create();
        $story = Story::factory()->create(['user_id' => $user->id]);
        Chapter::factory()->create(['story_id' => $story->id, 'number' => 1]);
        $otherStory = Story::factory()->create(['user_id' => $user->id]);
        Chapter::factory()->create(['story_id' => $otherStory->id, 'number' => 1]);

        $response = $this->actingAs($user)->get(route('stories.structure.index', $story));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('stories/Structure')
            ->where('story.slug', $story->slug)
            ->has('chapters', 1)
            ->has('povOptions')
        );
    }

    public function test_index_404s_on_foreign_story(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        Story::factory()->create(['user_id' => $other->id, 'slug' => 'theirs']);

        $response = $this->actingAs($owner)->get(route('stories.structure.index', ['story' => 'theirs']));

        $response->assertNotFound();
    }

    public function test_guests_cannot_open_the_structure_surface(): void
    {
        $story = Story::factory()->create();

        $response = $this->get(route('stories.structure.index', $story));

        $response->assertRedirect(route('login'));
    }

    public function test_authoring_a_chapter_scene_and_beat_makes_the_story_play_ready(): void
    {
        $user = User::factory()->create();
        $story = Story::factory()->create(['user_id' => $user->id]);
        $this->seedGlobalModelRoles();
        $character = Character::factory()->create(['story_id' => $story->id]);

        $this->actingAs($user)->post(route('stories.structure.chapters.store', $story), ['title' => 'One', 'pov_default' => 'third_limited']);
        $chapter = $story->chapters()->firstOrFail();

        $this->actingAs($user)->post(route('stories.structure.scenes.store', ['story' => $story->slug, 'chapter' => $chapter->id]), $this->scenePayload($character));
        $scene = Scene::firstOrFail();

        $this->actingAs($user)->post(route('stories.structure.beats.store', ['story' => $story->slug, 'chapter' => $chapter->id, 'scene' => $scene->id]), ['goal' => 'They meet']);

        $response = $this->actingAs($user)->get(route('stories.show', $story));
        $response->assertInertia(fn ($page) => $page->where('readiness.ready', true));
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
