<?php

namespace Tests\Feature\Database;

use App\Models\BeatRecord;
use App\Models\BeatTrueState;
use App\Models\Character;
use App\Models\InternalState;
use App\Models\PlaySession;
use App\Models\RelationshipEdge;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Structural tests for the save realm (S-4.2.1 / S-4.2.2).
 *
 * Asserts all sixteen save-realm tables and their key columns exist, the
 * relationship-edge direction uniqueness holds, and - the headline isolation
 * invariant - that a character's private `beat_true_states.private_text` lives
 * in a separate table that a surface-only read of `beat_records` cannot reach.
 */
class SaveRealmSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_all_save_realm_tables_exist(): void
    {
        $tables = [
            'play_sessions', 'review_items', 'relationship_edges', 'edge_axes',
            'axis_deltas', 'internal_states', 'active_emotions', 'acquired_sensitivities',
            'beat_records', 'beat_true_states', 'beat_witnesses', 'nudges',
            'scene_summaries', 'chapter_logs', 'events', 'llm_calls',
        ];

        foreach ($tables as $table) {
            $this->assertTrue(Schema::hasTable($table), "Missing save-realm table: {$table}");
        }
    }

    public function test_play_sessions_carry_the_narrator_loop_position(): void
    {
        $this->assertTrue(Schema::hasColumns('play_sessions', [
            'story_id', 'state_node', 'current_chapter_id', 'current_scene_id',
            'current_beat_id', 'nudge_level', 'resume_anchor', 'narrative_clock',
        ]));
    }

    public function test_relationship_edges_are_unique_per_direction(): void
    {
        $edge = RelationshipEdge::factory()->create();

        $this->expectException(QueryException::class);

        RelationshipEdge::factory()->create([
            'session_id' => $edge->session_id,
            'from_character_id' => $edge->from_character_id,
            'to_character_id' => $edge->to_character_id,
        ]);
    }

    public function test_beat_surface_is_structurally_isolated_from_private_true_state(): void
    {
        $secret = 'Inwardly she was furious and meant every cold word.';

        $record = BeatRecord::factory()->create(['surface' => 'She smiled politely and said nothing.']);
        BeatTrueState::factory()->create([
            'beat_record_id' => $record->id,
            'private_text' => $secret,
        ]);

        // The public layer has no private column at all: the isolation is
        // structural, not a runtime filter that could be bypassed.
        $this->assertFalse(Schema::hasColumn('beat_records', 'private_text'));
        $this->assertTrue(Schema::hasColumn('beat_true_states', 'private_text'));

        // A surface-only read therefore physically cannot return private text.
        $surfaceOnly = BeatRecord::query()->select('surface')->first();

        $this->assertNotNull($surfaceOnly);
        $this->assertArrayNotHasKey('private_text', $surfaceOnly->getAttributes());
        $this->assertStringNotContainsString($secret, (string) json_encode($surfaceOnly->getAttributes()));
    }

    public function test_internal_state_is_unique_per_session_and_character(): void
    {
        $session = PlaySession::factory()->create();
        $character = Character::factory()->create();

        InternalState::factory()->create([
            'session_id' => $session->id,
            'character_id' => $character->id,
        ]);

        $this->expectException(QueryException::class);

        InternalState::factory()->create([
            'session_id' => $session->id,
            'character_id' => $character->id,
        ]);
    }
}
