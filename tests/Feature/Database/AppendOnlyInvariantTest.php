<?php

namespace Tests\Feature\Database;

use App\Models\AxisDelta;
use App\Models\BeatRecord;
use App\Models\BeatTrueState;
use App\Models\BeatWitness;
use App\Models\Concerns\AppendOnly;
use App\Models\LlmCall;
use App\Models\Nudge;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;

/**
 * Enforcement tests for the append-only audit invariant (S-4.2.2, ADR 0012 §5).
 *
 * The six strict append-only tables may never be updated or deleted and must
 * carry no `updated_at`. These tests prove the {@see AppendOnly}
 * guard throws on both mutations, for every such model.
 */
class AppendOnlyInvariantTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, array{class-string<Model>, string}>
     */
    public static function appendOnlyModels(): array
    {
        return [
            'axis_deltas' => [AxisDelta::class, 'axis_deltas'],
            'beat_records' => [BeatRecord::class, 'beat_records'],
            'beat_true_states' => [BeatTrueState::class, 'beat_true_states'],
            'beat_witnesses' => [BeatWitness::class, 'beat_witnesses'],
            'nudges' => [Nudge::class, 'nudges'],
            'llm_calls' => [LlmCall::class, 'llm_calls'],
        ];
    }

    /**
     * @param  class-string<Model>  $modelClass
     */
    #[DataProvider('appendOnlyModels')]
    public function test_append_only_model_rejects_updates(string $modelClass, string $table): void
    {
        $model = $modelClass::factory()->create();

        $model->created_at = now()->addDay();

        $this->expectException(RuntimeException::class);

        $model->save();
    }

    /**
     * @param  class-string<Model>  $modelClass
     */
    #[DataProvider('appendOnlyModels')]
    public function test_append_only_model_rejects_deletes(string $modelClass, string $table): void
    {
        $model = $modelClass::factory()->create();

        $this->expectException(RuntimeException::class);

        $model->delete();
    }

    /**
     * @param  class-string<Model>  $modelClass
     */
    #[DataProvider('appendOnlyModels')]
    public function test_append_only_table_has_no_updated_at(string $modelClass, string $table): void
    {
        $this->assertFalse(
            Schema::hasColumn($table, 'updated_at'),
            "Append-only table {$table} must not carry updated_at."
        );
        $this->assertTrue(Schema::hasColumn($table, 'created_at'));
    }
}
