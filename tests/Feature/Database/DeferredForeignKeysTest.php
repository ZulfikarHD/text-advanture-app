<?php

namespace Tests\Feature\Database;

use App\Models\Chapter;
use App\Models\ChapterOutline;
use App\Models\Character;
use App\Models\CharacterCard;
use App\Models\Register;
use App\Models\RegisterArchetype;
use App\Models\ReviewItem;
use App\Models\Story;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Enforcement tests for the three FKs Sprint 3 deferred (PH-16, resolved S-4).
 *
 * `registers.archetype_id` -> `register_archetypes` and the two
 * `review_item_id` columns on `character_cards` / `chapter_outlines` ->
 * `review_items` are now real constraints: a dangling reference is rejected and
 * a valid reference resolves through the relation.
 */
class DeferredForeignKeysTest extends TestCase
{
    use RefreshDatabase;

    public function test_registers_archetype_id_foreign_key_is_enforced(): void
    {
        $character = Character::factory()->create();

        $this->expectException(QueryException::class);

        Register::factory()->create([
            'character_id' => $character->id,
            'archetype_id' => 999999,
        ]);
    }

    public function test_registers_archetype_relation_resolves_when_valid(): void
    {
        $character = Character::factory()->create();
        $archetype = RegisterArchetype::factory()->create();

        $register = Register::factory()->create([
            'character_id' => $character->id,
            'archetype_id' => $archetype->id,
        ]);

        $this->assertTrue($register->archetype->is($archetype));
    }

    public function test_character_cards_review_item_foreign_key_is_enforced(): void
    {
        $character = Character::factory()->create();
        $chapter = Chapter::factory()->create(['story_id' => $character->story_id]);

        $this->expectException(QueryException::class);

        CharacterCard::factory()->create([
            'character_id' => $character->id,
            'chapter_id' => $chapter->id,
            'review_item_id' => 999999,
        ]);
    }

    public function test_chapter_outlines_review_item_foreign_key_is_enforced(): void
    {
        $story = Story::factory()->create();

        $this->expectException(QueryException::class);

        ChapterOutline::factory()->create([
            'story_id' => $story->id,
            'review_item_id' => 999999,
        ]);
    }

    public function test_review_item_link_resolves_when_valid(): void
    {
        $story = Story::factory()->create();
        $review = ReviewItem::factory()->create();

        $outline = ChapterOutline::factory()->create([
            'story_id' => $story->id,
            'review_item_id' => $review->id,
        ]);

        $this->assertTrue($outline->reviewItem->is($review));
    }
}
