<?php

namespace Tests\Feature\Database;

use App\Models\Chapter;
use App\Models\Character;
use App\Models\CharacterCard;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Structural tests for the authoring realm (S-4.1.1).
 *
 * Asserts every authoring table and its key columns exist and that the
 * load-bearing composite uniqueness (`character_cards (character_id,
 * chapter_id)`) is enforced. Migrate/rollback reversibility lives in
 * {@see AuthoringRealmMigrationTest}.
 */
class AuthoringRealmSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_all_authoring_realm_tables_exist(): void
    {
        $tables = [
            'stories', 'chapters', 'characters', 'scenes', 'beats',
            'character_cards', 'reveal_ledger', 'lorebook_entries',
            'registers', 'sensitivities', 'chapter_outlines',
        ];

        foreach ($tables as $table) {
            $this->assertTrue(Schema::hasTable($table), "Missing authoring table: {$table}");
        }
    }

    public function test_stories_table_carries_owner_key(): void
    {
        $this->assertTrue(Schema::hasColumn('stories', 'user_id'));
    }

    public function test_character_cards_have_all_spec_columns(): void
    {
        $this->assertTrue(Schema::hasColumns('character_cards', [
            'character_id', 'chapter_id', 'folded_identity', 'knowledge_boundary',
            'disposition_priors', 'voice', 'tells', 'appearance',
            'compiled_source_hash', 'review_item_id',
        ]));
    }

    public function test_character_card_is_unique_per_character_and_chapter(): void
    {
        $character = Character::factory()->create();
        $chapter = Chapter::factory()->create(['story_id' => $character->story_id]);

        CharacterCard::factory()->create([
            'character_id' => $character->id,
            'chapter_id' => $chapter->id,
        ]);

        $this->expectException(QueryException::class);

        CharacterCard::factory()->create([
            'character_id' => $character->id,
            'chapter_id' => $chapter->id,
        ]);
    }
}
