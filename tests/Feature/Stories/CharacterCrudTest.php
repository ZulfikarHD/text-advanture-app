<?php

namespace Tests\Feature\Stories;

use App\Enums\ModelTier;
use App\Models\Character;
use App\Models\Story;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Feature tests for minimal manual character CRUD (S-1.1.1 / S-1.1.2).
 *
 * Covers: NPC create (no LLM, character + chapter-1 card persisted, story
 * scoped), the mandatory knowledge_boundary + folded_identity for an NPC, the
 * player slice (appearance + base_opacity only, no interiority, no edges), the
 * one-player-per-story rule, chapter-1 auto-ensure (created once, reused),
 * owner-scoped listing, update, delete, scoped child binding (cross-story 404s),
 * and the auth gate.
 */
class CharacterCrudTest extends TestCase
{
    use RefreshDatabase;

    /**
     * The minimal NPC payload used across the create tests.
     *
     * @return array<string, mixed>
     */
    private function npcPayload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Luna',
            'is_player' => false,
            'appearance' => 'small, sharp-eyed, fidgets with gloves',
            'base_opacity' => 70,
            'folded_identity' => 'a guarded classmate who deflects',
            'knowledge_boundary' => [
                'knows' => ['the password'],
                'does_not_know' => ['the diagnosis'],
            ],
        ], $overrides);
    }

    // --- Create (NPC) ---

    public function test_owner_can_create_a_non_player_character(): void
    {
        $user = User::factory()->create();
        $story = Story::factory()->create(['user_id' => $user->id]);

        $response = $this->actingAs($user)->post(route('stories.characters.store', $story), $this->npcPayload());

        $response->assertRedirect(route('stories.characters.index', $story));
        $this->assertDatabaseHas('characters', [
            'story_id' => $story->id,
            'name' => 'Luna',
            'is_player' => false,
            'model_tier' => ModelTier::Major->value,
        ]);
    }

    public function test_created_character_stores_a_chapter_one_card(): void
    {
        $user = User::factory()->create();
        $story = Story::factory()->create(['user_id' => $user->id]);

        $this->actingAs($user)->post(route('stories.characters.store', $story), $this->npcPayload());

        $card = Character::firstOrFail()->chapterOneCard;

        $this->assertSame('a guarded classmate who deflects', $card->folded_identity);
        $this->assertSame(['the password'], $card->knowledge_boundary['knows']);
        $this->assertSame(['the diagnosis'], $card->knowledge_boundary['does_not_know']);
    }

    public function test_creating_a_character_makes_no_llm_call(): void
    {
        $user = User::factory()->create();
        $story = Story::factory()->create(['user_id' => $user->id]);

        $this->actingAs($user)->post(route('stories.characters.store', $story), $this->npcPayload());

        // The manual slice records no LLM call (no API key required).
        $this->assertDatabaseEmpty('llm_calls');
    }

    public function test_non_player_character_requires_knowledge_boundary(): void
    {
        $user = User::factory()->create();
        $story = Story::factory()->create(['user_id' => $user->id]);

        $response = $this->actingAs($user)->post(
            route('stories.characters.store', $story),
            $this->npcPayload(['knowledge_boundary' => ['knows' => [], 'does_not_know' => []]]),
        );

        $response->assertSessionHasErrors('knowledge_boundary');
        $this->assertDatabaseEmpty('characters');
    }

    public function test_non_player_character_requires_folded_identity(): void
    {
        $user = User::factory()->create();
        $story = Story::factory()->create(['user_id' => $user->id]);

        $response = $this->actingAs($user)->post(
            route('stories.characters.store', $story),
            $this->npcPayload(['folded_identity' => '']),
        );

        $response->assertSessionHasErrors('folded_identity');
        $this->assertDatabaseEmpty('characters');
    }

    public function test_character_requires_a_name(): void
    {
        $user = User::factory()->create();
        $story = Story::factory()->create(['user_id' => $user->id]);

        $response = $this->actingAs($user)->post(
            route('stories.characters.store', $story),
            $this->npcPayload(['name' => '']),
        );

        $response->assertSessionHasErrors('name');
    }

    // --- Create (player) ---

    public function test_owner_can_create_a_player_character_with_appearance_only(): void
    {
        $user = User::factory()->create();
        $story = Story::factory()->create(['user_id' => $user->id]);

        $response = $this->actingAs($user)->post(route('stories.characters.store', $story), [
            'name' => 'Vixia',
            'is_player' => true,
            'appearance' => 'tall, quiet, watches the room',
            'base_opacity' => 40,
        ]);

        $response->assertRedirect(route('stories.characters.index', $story));
        $this->assertDatabaseHas('characters', [
            'story_id' => $story->id,
            'name' => 'Vixia',
            'is_player' => true,
            'model_tier' => ModelTier::Minor->value,
        ]);
    }

    public function test_player_carries_no_interiority_and_no_edges(): void
    {
        $user = User::factory()->create();
        $story = Story::factory()->create(['user_id' => $user->id]);

        $this->actingAs($user)->post(route('stories.characters.store', $story), [
            'name' => 'Vixia',
            'is_player' => true,
            'appearance' => 'tall, quiet, watches the room',
            'base_opacity' => 40,
        ]);

        $player = Character::firstOrFail();

        $this->assertSame([], $player->live_axes);
        $this->assertSame('', $player->chapterOneCard->folded_identity);
        $this->assertSame(
            ['knows' => [], 'does_not_know' => []],
            $player->chapterOneCard->knowledge_boundary,
        );
    }

    public function test_player_does_not_require_knowledge_boundary(): void
    {
        $user = User::factory()->create();
        $story = Story::factory()->create(['user_id' => $user->id]);

        $response = $this->actingAs($user)->post(route('stories.characters.store', $story), [
            'name' => 'Vixia',
            'is_player' => true,
            'appearance' => 'tall, quiet',
            'base_opacity' => 40,
        ]);

        $response->assertSessionHasNoErrors();
    }

    public function test_a_story_may_have_only_one_player(): void
    {
        $user = User::factory()->create();
        $story = Story::factory()->create(['user_id' => $user->id]);
        Character::factory()->player()->create(['story_id' => $story->id]);

        $response = $this->actingAs($user)->post(route('stories.characters.store', $story), [
            'name' => 'Second player',
            'is_player' => true,
            'appearance' => 'another would-be avatar',
            'base_opacity' => 50,
        ]);

        $response->assertSessionHasErrors('is_player');
        $this->assertDatabaseMissing('characters', ['name' => 'Second player']);
    }

    // --- Chapter-1 anchor ---

    public function test_first_character_auto_ensures_chapter_one(): void
    {
        $user = User::factory()->create();
        $story = Story::factory()->create(['user_id' => $user->id]);

        $this->actingAs($user)->post(route('stories.characters.store', $story), $this->npcPayload());

        $this->assertDatabaseHas('chapters', [
            'story_id' => $story->id,
            'number' => 1,
            'title' => 'Chapter 1',
        ]);
    }

    public function test_second_character_reuses_the_same_chapter_one(): void
    {
        $user = User::factory()->create();
        $story = Story::factory()->create(['user_id' => $user->id]);

        $this->actingAs($user)->post(route('stories.characters.store', $story), $this->npcPayload(['name' => 'Luna']));
        $this->actingAs($user)->post(route('stories.characters.store', $story), $this->npcPayload(['name' => 'Mira']));

        $this->assertSame(1, $story->chapters()->count());
    }

    // --- Index ---

    public function test_index_lists_only_this_storys_characters(): void
    {
        $user = User::factory()->create();
        $story = Story::factory()->create(['user_id' => $user->id]);
        $otherStory = Story::factory()->create(['user_id' => $user->id]);

        Character::factory()->count(2)->create(['story_id' => $story->id]);
        Character::factory()->create(['story_id' => $otherStory->id]);

        $response = $this->actingAs($user)->get(route('stories.characters.index', $story));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('stories/Characters')
            ->where('story.slug', $story->slug)
            ->has('characters', 2)
        );
    }

    public function test_index_404s_on_foreign_story(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        Story::factory()->create(['user_id' => $other->id, 'slug' => 'theirs']);

        $response = $this->actingAs($owner)->get(route('stories.characters.index', ['story' => 'theirs']));

        $response->assertNotFound();
    }

    public function test_creating_on_a_foreign_story_404s(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        Story::factory()->create(['user_id' => $other->id, 'slug' => 'theirs']);

        $response = $this->actingAs($owner)->post(route('stories.characters.store', ['story' => 'theirs']), $this->npcPayload());

        $response->assertNotFound();
        $this->assertDatabaseEmpty('characters');
    }

    // --- Update ---

    public function test_owner_can_update_a_character(): void
    {
        $user = User::factory()->create();
        $story = Story::factory()->create(['user_id' => $user->id]);
        $this->actingAs($user)->post(route('stories.characters.store', $story), $this->npcPayload());
        $character = Character::firstOrFail();

        $response = $this->actingAs($user)->put(
            route('stories.characters.update', ['story' => $story->slug, 'character' => $character->id]),
            $this->npcPayload(['name' => 'Luna Renamed']),
        );

        $response->assertRedirect(route('stories.characters.index', $story));
        $this->assertDatabaseHas('characters', ['id' => $character->id, 'name' => 'Luna Renamed']);
    }

    public function test_editing_the_existing_player_is_allowed(): void
    {
        $user = User::factory()->create();
        $story = Story::factory()->create(['user_id' => $user->id]);
        $player = Character::factory()->player()->create(['story_id' => $story->id]);

        $response = $this->actingAs($user)->put(
            route('stories.characters.update', ['story' => $story->slug, 'character' => $player->id]),
            [
                'name' => 'Player Renamed',
                'is_player' => true,
                'appearance' => 'updated look',
                'base_opacity' => 55,
            ],
        );

        $response->assertSessionHasNoErrors();
        $this->assertDatabaseHas('characters', ['id' => $player->id, 'name' => 'Player Renamed']);
    }

    public function test_update_404s_for_character_from_another_story(): void
    {
        $user = User::factory()->create();
        $storyA = Story::factory()->create(['user_id' => $user->id]);
        $storyB = Story::factory()->create(['user_id' => $user->id]);
        $characterB = Character::factory()->create(['story_id' => $storyB->id]);

        $response = $this->actingAs($user)->put(
            route('stories.characters.update', ['story' => $storyA->slug, 'character' => $characterB->id]),
            $this->npcPayload(),
        );

        $response->assertNotFound();
    }

    // --- Delete ---

    public function test_owner_can_delete_a_character(): void
    {
        $user = User::factory()->create();
        $story = Story::factory()->create(['user_id' => $user->id]);
        $character = Character::factory()->create(['story_id' => $story->id]);

        $response = $this->actingAs($user)->delete(
            route('stories.characters.destroy', ['story' => $story->slug, 'character' => $character->id]),
        );

        $response->assertRedirect(route('stories.characters.index', $story));
        $this->assertDatabaseMissing('characters', ['id' => $character->id]);
    }

    public function test_destroy_404s_for_character_from_another_story(): void
    {
        $user = User::factory()->create();
        $storyA = Story::factory()->create(['user_id' => $user->id]);
        $storyB = Story::factory()->create(['user_id' => $user->id]);
        $characterB = Character::factory()->create(['story_id' => $storyB->id]);

        $response = $this->actingAs($user)->delete(
            route('stories.characters.destroy', ['story' => $storyA->slug, 'character' => $characterB->id]),
        );

        $response->assertNotFound();
        $this->assertDatabaseHas('characters', ['id' => $characterB->id]);
    }

    // --- Auth gate ---

    public function test_guests_cannot_open_the_characters_surface(): void
    {
        $story = Story::factory()->create();

        $response = $this->get(route('stories.characters.index', $story));

        $response->assertRedirect(route('login'));
    }
}
