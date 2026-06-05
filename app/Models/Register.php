<?php

namespace App\Models;

use Database\Factories\RegisterFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Register - a character's conversational register (ADR 0006).
 *
 * Either instantiated from a shared `register_archetypes` row or bespoke
 * (`archetype_id` null). Unique per `(character_id, slug)`. The `archetype_id`
 * FK is added in Sprint 4 once the global library exists.
 *
 * @property int $id
 * @property int $character_id
 * @property string $slug
 * @property int|null $archetype_id
 * @property array<string, mixed> $dimensions
 * @property string|null $speech_ref
 * @property array<string, mixed> $tells
 * @property bool $is_pinned
 */
#[Fillable([
    'character_id',
    'slug',
    'archetype_id',
    'dimensions',
    'speech_ref',
    'tells',
    'is_pinned',
])]
class Register extends Model
{
    /** @use HasFactory<RegisterFactory> */
    use HasFactory;

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
            'dimensions' => 'array',
            'tells' => 'array',
            'is_pinned' => 'boolean',
        ];
    }
}
