<?php

namespace App\Models;

use App\Enums\EventType;
use App\Enums\Handoff;
use Database\Factories\EventFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Event - one entry in a save's immediate-context timeline (ADR 0016).
 *
 * A narration / player input / NPC action / system entry with its optional
 * narrator handoff signal and token estimate for the bounded immediate window.
 * Compacted into `scene_summaries` at SCENE_DONE. Carries only `created_at`.
 *
 * @property int $id
 * @property int $session_id
 * @property int|null $beat_id
 * @property EventType $type
 * @property int|null $character_id
 * @property string $content
 * @property array<string, mixed>|null $delivery
 * @property Handoff|null $handoff
 * @property int|null $token_estimate
 * @property Carbon $created_at
 */
#[Fillable([
    'session_id',
    'beat_id',
    'type',
    'character_id',
    'content',
    'delivery',
    'handoff',
    'token_estimate',
])]
class Event extends Model
{
    /** @use HasFactory<EventFactory> */
    use HasFactory;

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
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => EventType::class,
            'delivery' => 'array',
            'handoff' => Handoff::class,
            'token_estimate' => 'integer',
            'created_at' => 'datetime',
        ];
    }
}
