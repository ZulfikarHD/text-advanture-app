<?php

namespace App\Models;

use Database\Factories\RelationshipEdgeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * RelationshipEdge - a directed `from -> to` relationship in a save (ADR 0002).
 *
 * `A->B` is distinct from `B->A`; unique per
 * `(session_id, from_character_id, to_character_id)`. Per-axis values hang off
 * it in `edge_axes`; the append-only change history is in `axis_deltas`.
 *
 * @property int $id
 * @property int $session_id
 * @property int $from_character_id
 * @property int $to_character_id
 * @property string $register_base
 * @property array<string, mixed>|null $register_overrides
 * @property array<int|string, mixed>|null $topic_flags
 * @property array<string, mixed>|null $meta
 */
#[Fillable([
    'session_id',
    'from_character_id',
    'to_character_id',
    'register_base',
    'register_overrides',
    'topic_flags',
    'meta',
])]
class RelationshipEdge extends Model
{
    /** @use HasFactory<RelationshipEdgeFactory> */
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
    public function fromCharacter(): BelongsTo
    {
        return $this->belongsTo(Character::class, 'from_character_id');
    }

    /**
     * @return BelongsTo<Character, $this>
     */
    public function toCharacter(): BelongsTo
    {
        return $this->belongsTo(Character::class, 'to_character_id');
    }

    /**
     * @return HasMany<EdgeAxis, $this>
     */
    public function axes(): HasMany
    {
        return $this->hasMany(EdgeAxis::class);
    }

    /**
     * @return HasMany<AxisDelta, $this>
     */
    public function deltas(): HasMany
    {
        return $this->hasMany(AxisDelta::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'register_overrides' => 'array',
            'topic_flags' => 'array',
            'meta' => 'array',
        ];
    }
}
