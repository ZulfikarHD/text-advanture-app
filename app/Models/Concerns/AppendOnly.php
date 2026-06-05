<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Model;
use RuntimeException;

/**
 * Enforces the append-only audit invariant at the data layer (ADR 0012 §5).
 *
 * The audit tables (`axis_deltas`, `beat_records` + children, `nudges`,
 * `llm_calls`) record history that must never be silently rewritten:
 * corrections are new rows through the review gate, not mutations. This trait
 * makes that a hard guard - any attempt to update or delete an existing row
 * throws a {@see RuntimeException} rather than failing silently.
 *
 * Models using this trait must also declare `const UPDATED_AT = null;` so the
 * table carries only `created_at` (the migration likewise omits `updated_at`).
 */
trait AppendOnly
{
    /**
     * Boot the trait: block updates and deletes on append-only models.
     */
    public static function bootAppendOnly(): void
    {
        static::updating(function (Model $model): void {
            throw new RuntimeException(
                static::class.' is append-only; existing rows may not be updated (corrections are new rows via the review gate).'
            );
        });

        static::deleting(function (Model $model): void {
            throw new RuntimeException(
                static::class.' is append-only; rows may not be deleted.'
            );
        });
    }
}
