<?php

namespace App\Models;

use App\Models\Concerns\AppendOnly;
use Database\Factories\BeatRecordFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * BeatRecord - the public surface layer of a played beat (ADR 0010).
 *
 * APPEND-ONLY ({@see AppendOnly}); carries only `created_at`. `surface` is the
 * ONLY cross-agent layer (observable behavior + dialogue + hedged reads); each
 * character's private feeling lives in the separate {@see BeatTrueState} child
 * so a surface-only read physically cannot reach it (structural isolation).
 *
 * @property int $id
 * @property int $session_id
 * @property int|null $beat_id
 * @property string $surface
 * @property string $pov_anchor
 * @property Carbon $created_at
 */
#[Fillable(['session_id', 'beat_id', 'surface', 'pov_anchor'])]
class BeatRecord extends Model
{
    /** @use HasFactory<BeatRecordFactory> */
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
     * @return BelongsTo<Beat, $this>
     */
    public function beat(): BelongsTo
    {
        return $this->belongsTo(Beat::class);
    }

    /**
     * Per-character private true-states for this beat (never cross-fed).
     *
     * @return HasMany<BeatTrueState, $this>
     */
    public function trueStates(): HasMany
    {
        return $this->hasMany(BeatTrueState::class);
    }

    /**
     * @return HasMany<BeatWitness, $this>
     */
    public function witnesses(): HasMany
    {
        return $this->hasMany(BeatWitness::class);
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
