<?php

namespace App\Models;

use App\Models\Concerns\AppendOnly;
use Database\Factories\BeatTrueStateFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * BeatTrueState - a character's PRIVATE feeling/intent for a beat (ADR 0010).
 *
 * APPEND-ONLY ({@see AppendOnly}); carries only `created_at`. Deliberately a
 * SEPARATE table from {@see BeatRecord} (not a column) so that a query reading
 * only the public `surface` cannot pull any character's `private_text`. Reaches
 * its own character only via its `[SELF]` block - never cross-fed.
 *
 * @property int $id
 * @property int $beat_record_id
 * @property int $character_id
 * @property string $private_text
 * @property Carbon $created_at
 */
#[Fillable(['beat_record_id', 'character_id', 'private_text'])]
class BeatTrueState extends Model
{
    /** @use HasFactory<BeatTrueStateFactory> */
    use AppendOnly, HasFactory;

    public const UPDATED_AT = null;

    /**
     * @return BelongsTo<BeatRecord, $this>
     */
    public function beatRecord(): BelongsTo
    {
        return $this->belongsTo(BeatRecord::class);
    }

    /**
     * @return BelongsTo<Character, $this>
     */
    public function character(): BelongsTo
    {
        return $this->belongsTo(Character::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
        ];
    }
}
