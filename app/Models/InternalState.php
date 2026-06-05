<?php

namespace App\Models;

use Database\Factories\InternalStateFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * InternalState - a character's private `[SELF]` for a save (ADR 0014).
 *
 * Unique per `(session_id, character_id)`. `mood` is a derived rollup of the
 * `active_emotions` children (optionally pinned by `mood_override`); also holds
 * motivation and masks.
 *
 * @property int $id
 * @property int $session_id
 * @property int $character_id
 * @property string|null $mood
 * @property string|null $mood_override
 * @property array<string, mixed>|null $motivation
 * @property array<int, array<string, mixed>>|null $masks
 * @property Carbon|null $last_clocked_at
 */
#[Fillable([
    'session_id',
    'character_id',
    'mood',
    'mood_override',
    'motivation',
    'masks',
    'last_clocked_at',
])]
class InternalState extends Model
{
    /** @use HasFactory<InternalStateFactory> */
    use HasFactory;

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
     * @return HasMany<ActiveEmotion, $this>
     */
    public function activeEmotions(): HasMany
    {
        return $this->hasMany(ActiveEmotion::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'motivation' => 'array',
            'masks' => 'array',
            'last_clocked_at' => 'datetime',
        ];
    }
}
