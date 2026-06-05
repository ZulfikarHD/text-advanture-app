<?php

namespace App\Models;

use App\Enums\ProducerType;
use App\Enums\ReviewStatus;
use App\Models\Concerns\BelongsToOwner;
use Database\Factories\ReviewItemFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * ReviewItem - one entry in the shared review queue (ADR 0003/0012 §5).
 *
 * Polymorphic by `producer_type`; moves pending -> accepted | edited | rejected
 * with reviewer + timestamp. A null `session_id` is an authoring-time compile
 * (card/outline/bible) - a deliberate authoring-realm row in a save-realm
 * table. Edits are captured separately in `edited_payload`. Owner-scoped via a
 * direct `user_id` so null-session proposals stay isolated per account.
 *
 * @property int $id
 * @property int|null $session_id
 * @property ProducerType $producer_type
 * @property int|null $producer_id
 * @property array<string, mixed> $payload
 * @property ReviewStatus $status
 * @property array<string, mixed>|null $edited_payload
 * @property Carbon|null $reviewed_at
 * @property string|null $reviewed_by
 */
#[Fillable([
    'user_id',
    'session_id',
    'producer_type',
    'producer_id',
    'payload',
    'status',
    'edited_payload',
    'reviewed_at',
    'reviewed_by',
])]
class ReviewItem extends Model
{
    /** @use HasFactory<ReviewItemFactory> */
    use BelongsToOwner, HasFactory;

    /**
     * @return BelongsTo<PlaySession, $this>
     */
    public function session(): BelongsTo
    {
        return $this->belongsTo(PlaySession::class, 'session_id');
    }

    /**
     * The payload to commit: the author's edits when present, else the original.
     *
     * @return array<string, mixed>
     */
    public function committedPayload(): array
    {
        return $this->edited_payload ?? $this->payload;
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'producer_type' => ProducerType::class,
            'payload' => 'array',
            'status' => ReviewStatus::class,
            'edited_payload' => 'array',
            'reviewed_at' => 'datetime',
        ];
    }
}
