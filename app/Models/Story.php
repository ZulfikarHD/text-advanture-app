<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOwner;
use Database\Factories\StoryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Story - root of the authoring realm and the first owner-scoped product model.
 *
 * Owned via {@see BelongsToOwner}: the owner global scope hides other users'
 * stories and `user_id` is stamped on create. Child authoring rows (chapters,
 * characters, ...) carry no `user_id`; they inherit isolation transitively
 * through their story. Immutable at runtime (ADR 0012).
 *
 * @property int $id
 * @property int $user_id
 * @property string $slug
 * @property string $title
 * @property string|null $description
 * @property array<string, mixed>|null $settings
 */
#[Fillable(['user_id', 'slug', 'title', 'description', 'settings'])]
class Story extends Model
{
    /** @use HasFactory<StoryFactory> */
    use BelongsToOwner, HasFactory;

    /**
     * @return HasMany<Chapter, $this>
     */
    public function chapters(): HasMany
    {
        return $this->hasMany(Chapter::class);
    }

    /**
     * @return HasMany<Character, $this>
     */
    public function characters(): HasMany
    {
        return $this->hasMany(Character::class);
    }

    /**
     * @return HasMany<LorebookEntry, $this>
     */
    public function lorebookEntries(): HasMany
    {
        return $this->hasMany(LorebookEntry::class);
    }

    /**
     * @return HasMany<RevealLedger, $this>
     */
    public function revealLedgerEntries(): HasMany
    {
        return $this->hasMany(RevealLedger::class);
    }

    /**
     * @return HasMany<ChapterOutline, $this>
     */
    public function chapterOutlines(): HasMany
    {
        return $this->hasMany(ChapterOutline::class);
    }

    /**
     * Save-realm playthroughs forked from this story's authoring template.
     *
     * Used only for the read-derived save count on the story overview; saves
     * are never copied by duplicate/import (ADR 0012).
     *
     * @return HasMany<PlaySession, $this>
     */
    public function playSessions(): HasMany
    {
        return $this->hasMany(PlaySession::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'settings' => 'array',
        ];
    }
}
