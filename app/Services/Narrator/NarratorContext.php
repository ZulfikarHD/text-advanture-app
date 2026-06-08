<?php

namespace App\Services\Narrator;

use App\Models\Beat;
use App\Models\Chapter;
use App\Models\Character;
use App\Models\LorebookEntry;
use App\Models\PlaySession;
use App\Models\Scene;
use Illuminate\Support\Collection;

/**
 * The resolved data a narrator turn folds its prompt blocks from (ADR 0016 §6).
 *
 * Built once per turn by {@see NarratorPromptAssembler} so block producers stay
 * pure — they read this context, never the database. Scene character references
 * are slugs (PH-35), so the cast is keyed by slug for resolution; appearance is
 * read from each character's card at the session's current chapter.
 */
final readonly class NarratorContext
{
    /**
     * @param  PlaySession  $session  The save the turn runs on (carries position + resume anchor).
     * @param  Scene|null  $scene  The current authoring scene, or null when unpositioned.
     * @param  Beat|null  $beat  The current authoring beat, or null when unpositioned.
     * @param  Chapter|null  $chapter  The current authoring chapter, or null when unpositioned.
     * @param  Collection<string, Character>  $cast  The story cast keyed by slug, current-chapter card eager-loaded.
     * @param  Collection<int, LorebookEntry>  $lorebookEntries  The story's lorebook with `minRevealChapter` loaded.
     * @param  string|null  $sceneSummaryText  The latest scene-summary rollup for this scene, if any (E5.2 seam).
     */
    public function __construct(
        public PlaySession $session,
        public ?Scene $scene,
        public ?Beat $beat,
        public ?Chapter $chapter,
        public Collection $cast,
        public Collection $lorebookEntries,
        public ?string $sceneSummaryText,
    ) {}

    /**
     * The display name for a character slug, or null when the slug is unknown.
     *
     * @param  string|null  $slug  A scene character reference (slug, not FK).
     */
    public function characterName(?string $slug): ?string
    {
        if ($slug === null || $slug === '') {
            return null;
        }

        return $this->cast->get($slug)?->name;
    }

    /**
     * The characters present in the current scene, resolved from their slugs.
     *
     * Unknown slugs (a removed/renamed character) are dropped silently.
     *
     * @return Collection<int, Character>
     */
    public function presentCharacters(): Collection
    {
        $slugs = $this->scene?->present_characters ?? [];

        return (new Collection($slugs))
            ->map(fn (string $slug): ?Character => $this->cast->get($slug))
            ->filter()
            ->values();
    }

    /**
     * The appearance authored on a character's current-chapter card, if any.
     *
     * @param  Character  $character  A character from {@see self::presentCharacters()}.
     */
    public function appearanceFor(Character $character): ?string
    {
        return $character->relationLoaded('cards')
            ? $character->cards->first()?->appearance
            : null;
    }

    /**
     * The number of the chapter the turn is positioned in, for the reveal gate.
     */
    public function currentChapterNumber(): ?int
    {
        return $this->chapter?->number;
    }

    /**
     * The text lorebook keyword matching runs against this turn.
     *
     * This phase has no committed prose history yet (E5.2), so the sample is the
     * authored scene surface: setting, tone, POV anchor, beat goal, the present
     * cast's names, and any scene summary. Producers must not assume more.
     */
    public function sampleText(): string
    {
        $parts = [];

        if ($this->scene !== null) {
            $parts[] = $this->scene->setting;
            $parts[] = $this->scene->tone;
            $parts[] = $this->characterName($this->scene->pov_anchor) ?? $this->scene->pov_anchor;
        }

        $parts[] = $this->beat?->goal;

        foreach ($this->presentCharacters() as $character) {
            $parts[] = $character->name;
        }

        $parts[] = $this->sceneSummaryText;

        return (new Collection($parts))
            ->filter(fn (?string $part): bool => $part !== null && trim($part) !== '')
            ->implode(' ');
    }
}
