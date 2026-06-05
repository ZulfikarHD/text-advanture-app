<?php

namespace App\Models;

use App\Enums\LlmCallStatus;
use App\Enums\LlmRole;
use App\Models\Concerns\AppendOnly;
use App\Models\Concerns\BelongsToOwner;
use Database\Factories\LlmCallFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * LlmCall - one entry in the append-only model-call log (ADR 0017).
 *
 * APPEND-ONLY ({@see AppendOnly}); carries only `created_at`. Records role,
 * resolved model, token usage, provider cost (USD micro-units), latency, and
 * status per call. Owner-scoped via {@see BelongsToOwner} (`user_id`) so one
 * user can never read another's call history. `messages` is debug-gated and
 * save-realm-sensitive (it may embed a character's `true_state`), so it is
 * marked Hidden and is never an agent-readable source. Cost is provider-
 * reported USD micro-units, displayed as USD.
 *
 * @property int $id
 * @property int|null $user_id
 * @property int|null $session_id
 * @property int|null $story_id
 * @property LlmRole $role
 * @property string $model_slug
 * @property LlmCallStatus $status
 * @property int|null $prompt_tokens
 * @property int|null $completion_tokens
 * @property int|null $cost_micros_usd
 * @property int|null $latency_ms
 * @property string|null $error
 * @property int|null $review_item_id
 * @property array<int, mixed>|null $messages
 * @property Carbon $created_at
 */
#[Fillable([
    'user_id',
    'session_id',
    'story_id',
    'role',
    'model_slug',
    'status',
    'prompt_tokens',
    'completion_tokens',
    'cost_micros_usd',
    'latency_ms',
    'error',
    'review_item_id',
    'messages',
])]
#[Hidden(['messages'])]
class LlmCall extends Model
{
    /** @use HasFactory<LlmCallFactory> */
    use AppendOnly, BelongsToOwner, HasFactory;

    public const UPDATED_AT = null;

    /**
     * @return BelongsTo<PlaySession, $this>
     */
    public function session(): BelongsTo
    {
        return $this->belongsTo(PlaySession::class, 'session_id');
    }

    /**
     * @return BelongsTo<Story, $this>
     */
    public function story(): BelongsTo
    {
        return $this->belongsTo(Story::class);
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
            'role' => LlmRole::class,
            'status' => LlmCallStatus::class,
            'prompt_tokens' => 'integer',
            'completion_tokens' => 'integer',
            'cost_micros_usd' => 'integer',
            'latency_ms' => 'integer',
            'messages' => 'array',
            'created_at' => 'datetime',
        ];
    }
}
