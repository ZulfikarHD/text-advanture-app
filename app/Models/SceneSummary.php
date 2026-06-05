<?php

namespace App\Models;

use Database\Factories\SceneSummaryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * SceneSummary - a scene compressed at SCENE_DONE (ADR 0015/0016).
 *
 * Context-memory rollup with flags tracking whether batched drift and decay
 * were applied. Carries only `created_at` (no `updated_at`); the applied flags
 * may be toggled once, so it is not append-only.
 *
 * @property int $id
 * @property int $session_id
 * @property int|null $scene_id
 * @property string $summary
 * @property bool $drift_applied
 * @property bool $decay_applied
 * @property Carbon $created_at
 */
#[Fillable(['session_id', 'scene_id', 'summary', 'drift_applied', 'decay_applied'])]
class SceneSummary extends Model
{
    /** @use HasFactory<SceneSummaryFactory> */
    use HasFactory;

    public const UPDATED_AT = null;

    /**
     * @return BelongsTo<PlaySession, $this>
     */
    public function session(): BelongsTo
    {
        return $this->belongsTo(PlaySession::class, 'session_id');
    }

    /**
     * @return BelongsTo<Scene, $this>
     */
    public function scene(): BelongsTo
    {
        return $this->belongsTo(Scene::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'drift_applied' => 'boolean',
            'decay_applied' => 'boolean',
            'created_at' => 'datetime',
        ];
    }
}
