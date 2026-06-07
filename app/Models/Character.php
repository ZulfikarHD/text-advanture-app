<?php

namespace App\Models;

use App\Enums\ModelTier;
use Database\Factories\CharacterFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Character - a member of a story's cast (ADR 0001/0002/0007).
 *
 * Authoring-realm child of {@see Story}; unique per `(story_id, slug)`.
 * `bible_path` points at repo markdown that is never injected (ADR 0001).
 *
 * @property int $id
 * @property int $story_id
 * @property string $slug
 * @property string $name
 * @property string|null $bible_path
 * @property int $base_opacity
 * @property array<int, string> $live_axes
 * @property ModelTier $model_tier
 * @property bool $is_player
 */
#[Fillable([
    'story_id',
    'slug',
    'name',
    'bible_path',
    'base_opacity',
    'live_axes',
    'model_tier',
    'is_player',
])]
class Character extends Model
{
    /** @use HasFactory<CharacterFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<Story, $this>
     */
    public function story(): BelongsTo
    {
        return $this->belongsTo(Story::class);
    }

    /**
     * @return HasMany<CharacterCard, $this>
     */
    public function cards(): HasMany
    {
        return $this->hasMany(CharacterCard::class);
    }

    /**
     * The chapter-1 card — the minimal manual slice authored in E1.1.
     *
     * A character's `appearance`, `folded_identity`, and `knowledge_boundary`
     * live on this per-`(character, chapter)` card; this phase authors only the
     * `Chapter 1` snapshot (later chapters recompile per ADR 0013 §4).
     *
     * @return HasOne<CharacterCard, $this>
     */
    public function chapterOneCard(): HasOne
    {
        return $this->hasOne(CharacterCard::class)
            ->whereHas('chapter', fn ($query) => $query->where('number', 1));
    }

    /**
     * @return HasMany<Register, $this>
     */
    public function registers(): HasMany
    {
        return $this->hasMany(Register::class);
    }

    /**
     * @return HasMany<Sensitivity, $this>
     */
    public function sensitivities(): HasMany
    {
        return $this->hasMany(Sensitivity::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'base_opacity' => 'integer',
            'live_axes' => 'array',
            'model_tier' => ModelTier::class,
            'is_player' => 'boolean',
        ];
    }
}
