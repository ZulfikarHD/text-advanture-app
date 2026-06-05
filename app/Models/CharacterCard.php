<?php

namespace App\Models;

use Database\Factories\CharacterCardFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * CharacterCard - a per-(character, chapter) compiled, spoiler-free snapshot
 * (ADR 0001/0013).
 *
 * Unique per `(character_id, chapter_id)`: a character has exactly one card per
 * chapter, recompiled as chapters advance. `review_item_id` links to the
 * save-realm `card_compile` review record (FK added in Sprint 4).
 *
 * @property int $id
 * @property int $character_id
 * @property int $chapter_id
 * @property string $folded_identity
 * @property array<string, mixed> $knowledge_boundary
 * @property array<string, mixed> $disposition_priors
 * @property array<string, mixed> $voice
 * @property array<string, mixed> $tells
 * @property string|null $appearance
 * @property string|null $compiled_source_hash
 * @property int|null $review_item_id
 */
#[Fillable([
    'character_id',
    'chapter_id',
    'folded_identity',
    'knowledge_boundary',
    'disposition_priors',
    'voice',
    'tells',
    'appearance',
    'compiled_source_hash',
    'review_item_id',
])]
class CharacterCard extends Model
{
    /** @use HasFactory<CharacterCardFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<Character, $this>
     */
    public function character(): BelongsTo
    {
        return $this->belongsTo(Character::class);
    }

    /**
     * @return BelongsTo<Chapter, $this>
     */
    public function chapter(): BelongsTo
    {
        return $this->belongsTo(Chapter::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'knowledge_boundary' => 'array',
            'disposition_priors' => 'array',
            'voice' => 'array',
            'tells' => 'array',
        ];
    }
}
