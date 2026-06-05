<?php

namespace App\Models;

use App\Enums\Axis;
use App\Enums\DeltaChannel;
use App\Enums\DeltaDirection;
use App\Enums\DeltaSource;
use App\Models\Concerns\AppendOnly;
use Database\Factories\AxisDeltaFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * AxisDelta - one immutable entry in the axis change log (ADR 0003).
 *
 * APPEND-ONLY: carries only `created_at` and may never be updated or deleted
 * ({@see AppendOnly}); corrections are new rows through the review gate. Every
 * row records a mandatory `trigger` and the before/after value for audit.
 *
 * @property int $id
 * @property int $relationship_edge_id
 * @property Axis $axis
 * @property DeltaDirection $direction
 * @property string $magnitude
 * @property DeltaChannel $channel
 * @property string $trigger
 * @property string|null $confidence
 * @property int $value_before
 * @property int $value_after
 * @property DeltaSource $source
 * @property int|null $review_item_id
 * @property Carbon $created_at
 */
#[Fillable([
    'relationship_edge_id',
    'axis',
    'direction',
    'magnitude',
    'channel',
    'trigger',
    'confidence',
    'value_before',
    'value_after',
    'source',
    'review_item_id',
])]
class AxisDelta extends Model
{
    /** @use HasFactory<AxisDeltaFactory> */
    use AppendOnly, HasFactory;

    public const UPDATED_AT = null;

    /**
     * @return BelongsTo<RelationshipEdge, $this>
     */
    public function relationshipEdge(): BelongsTo
    {
        return $this->belongsTo(RelationshipEdge::class);
    }

    /**
     * @return BelongsTo<ReviewItem, $this>
     */
    public function reviewItem(): BelongsTo
    {
        return $this->belongsTo(ReviewItem::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'axis' => Axis::class,
            'direction' => DeltaDirection::class,
            'magnitude' => 'decimal:2',
            'channel' => DeltaChannel::class,
            'confidence' => 'decimal:2',
            'value_before' => 'integer',
            'value_after' => 'integer',
            'source' => DeltaSource::class,
            'created_at' => 'datetime',
        ];
    }
}
