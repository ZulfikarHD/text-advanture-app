<?php

namespace App\Models;

use App\Enums\EmotionSource;
use Database\Factories\ActiveEmotionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * ActiveEmotion - a single feeling on its own clock (ADR 0014 §5).
 *
 * Child of {@see InternalState}; a free-text emotion with intensity, resting
 * baseline (0 for acute, non-zero for chronic), reversion rate toward baseline,
 * and a bounded off-screen drift cap.
 *
 * @property int $id
 * @property int $internal_state_id
 * @property string $emotion
 * @property int $intensity
 * @property int $baseline
 * @property string|null $reversion_rate
 * @property int $drift_cap
 * @property EmotionSource $source
 * @property Carbon|null $installed_at
 * @property Carbon|null $last_clocked_at
 */
#[Fillable([
    'internal_state_id',
    'emotion',
    'intensity',
    'baseline',
    'reversion_rate',
    'drift_cap',
    'source',
    'installed_at',
    'last_clocked_at',
])]
class ActiveEmotion extends Model
{
    /** @use HasFactory<ActiveEmotionFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<InternalState, $this>
     */
    public function internalState(): BelongsTo
    {
        return $this->belongsTo(InternalState::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'intensity' => 'integer',
            'baseline' => 'integer',
            'reversion_rate' => 'decimal:2',
            'drift_cap' => 'integer',
            'source' => EmotionSource::class,
            'installed_at' => 'datetime',
            'last_clocked_at' => 'datetime',
        ];
    }
}
