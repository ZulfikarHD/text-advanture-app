<?php

namespace App\Models;

use App\Enums\NudgeLevel;
use App\Enums\NudgeSource;
use App\Models\Concerns\AppendOnly;
use Database\Factories\NudgeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Nudge - a directed-pressure instruction framed onto a character (ADR
 * 0008/0015).
 *
 * APPEND-ONLY ({@see AppendOnly}); carries only `created_at` - a re-issue is a
 * new row. `text` is internal-framed and leak-checked; `is_break_glass` marks
 * the hard directive that is always logged.
 *
 * @property int $id
 * @property int $session_id
 * @property int|null $beat_id
 * @property int $character_id
 * @property array<int, string> $kind
 * @property NudgeLevel $level
 * @property string $text
 * @property string|null $target
 * @property string|null $goal
 * @property NudgeSource $source
 * @property bool $is_break_glass
 * @property int|null $review_item_id
 * @property Carbon $created_at
 */
#[Fillable([
    'session_id',
    'beat_id',
    'character_id',
    'kind',
    'level',
    'text',
    'target',
    'goal',
    'source',
    'is_break_glass',
    'review_item_id',
])]
class Nudge extends Model
{
    /** @use HasFactory<NudgeFactory> */
    use AppendOnly, HasFactory;

    public const UPDATED_AT = null;

    /**
     * @return BelongsTo<PlaySession, $this>
     */
    public function session(): BelongsTo
    {
        return $this->belongsTo(PlaySession::class, 'session_id');
    }

    /**
     * @return BelongsTo<Character, $this>
     */
    public function character(): BelongsTo
    {
        return $this->belongsTo(Character::class);
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
            'kind' => 'array',
            'level' => NudgeLevel::class,
            'source' => NudgeSource::class,
            'is_break_glass' => 'boolean',
            'created_at' => 'datetime',
        ];
    }
}
