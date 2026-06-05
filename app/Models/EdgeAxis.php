<?php

namespace App\Models;

use App\Enums\AwarenessMode;
use App\Enums\Axis;
use Database\Factories\EdgeAxisFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * EdgeAxis - the materialized current value of one axis on an edge (ADR
 * 0002/0004).
 *
 * Holds the value plus the soft/hard clamps, asymmetric gain/loss rates,
 * peak high-water marks, decay baseline, and the latched scar. Unique per
 * `(relationship_edge_id, axis)`. Effective floor = max(soft_floor,
 * scar.floor).
 *
 * @property int $id
 * @property int $relationship_edge_id
 * @property Axis $axis
 * @property int $value
 * @property AwarenessMode $awareness_mode
 * @property int $soft_floor
 * @property int $soft_cap
 * @property int $hard_floor
 * @property int $hard_cap
 * @property string $gain_rate
 * @property string $loss_rate
 * @property int $peak_up
 * @property int $peak_down
 * @property int $baseline
 * @property int|null $latch_threshold
 * @property array<string, mixed>|null $scar
 */
#[Fillable([
    'relationship_edge_id',
    'axis',
    'value',
    'awareness_mode',
    'soft_floor',
    'soft_cap',
    'hard_floor',
    'hard_cap',
    'gain_rate',
    'loss_rate',
    'peak_up',
    'peak_down',
    'baseline',
    'latch_threshold',
    'scar',
])]
class EdgeAxis extends Model
{
    /** @use HasFactory<EdgeAxisFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<RelationshipEdge, $this>
     */
    public function relationshipEdge(): BelongsTo
    {
        return $this->belongsTo(RelationshipEdge::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'axis' => Axis::class,
            'value' => 'integer',
            'awareness_mode' => AwarenessMode::class,
            'soft_floor' => 'integer',
            'soft_cap' => 'integer',
            'hard_floor' => 'integer',
            'hard_cap' => 'integer',
            'gain_rate' => 'decimal:2',
            'loss_rate' => 'decimal:2',
            'peak_up' => 'integer',
            'peak_down' => 'integer',
            'baseline' => 'integer',
            'latch_threshold' => 'integer',
            'scar' => 'array',
        ];
    }
}
