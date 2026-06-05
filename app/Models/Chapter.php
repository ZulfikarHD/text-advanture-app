<?php

namespace App\Models;

use Database\Factories\ChapterFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Chapter - an ordered chapter within a story (ADR 0015).
 *
 * Authoring-realm child of {@see Story}; unique per `(story_id, number)`.
 *
 * @property int $id
 * @property int $story_id
 * @property int $number
 * @property string $title
 * @property string $pov_default
 * @property string|null $outline
 * @property int|null $word_cap
 */
#[Fillable(['story_id', 'number', 'title', 'pov_default', 'outline', 'word_cap'])]
class Chapter extends Model
{
    /** @use HasFactory<ChapterFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<Story, $this>
     */
    public function story(): BelongsTo
    {
        return $this->belongsTo(Story::class);
    }

    /**
     * @return HasMany<Scene, $this>
     */
    public function scenes(): HasMany
    {
        return $this->hasMany(Scene::class);
    }

    /**
     * @return HasMany<CharacterCard, $this>
     */
    public function characterCards(): HasMany
    {
        return $this->hasMany(CharacterCard::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'number' => 'integer',
            'word_cap' => 'integer',
        ];
    }
}
