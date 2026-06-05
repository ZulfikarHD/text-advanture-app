<?php

namespace App\Models;

use App\Enums\Fidelity;
use App\Models\Concerns\AppendOnly;
use Database\Factories\BeatWitnessFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * BeatWitness - who saw a beat and at what fidelity (ADR 0007).
 *
 * APPEND-ONLY ({@see AppendOnly}); carries only `created_at`. The fidelity
 * (full / overheard / partial) drives how the beat excerpt is filtered and
 * projected into each witnessing NPC's context.
 *
 * @property int $id
 * @property int $beat_record_id
 * @property int $character_id
 * @property Fidelity $fidelity
 * @property Carbon $created_at
 */
#[Fillable(['beat_record_id', 'character_id', 'fidelity'])]
class BeatWitness extends Model
{
    /** @use HasFactory<BeatWitnessFactory> */
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
            'fidelity' => Fidelity::class,
            'created_at' => 'datetime',
        ];
    }
}
