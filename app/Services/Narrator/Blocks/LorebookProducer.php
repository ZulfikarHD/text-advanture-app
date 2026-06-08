<?php

namespace App\Services\Narrator\Blocks;

use App\Models\LorebookEntry;
use App\Services\LorebookKeywordMatcher;
use App\Services\Narrator\NarratorContext;

/**
 * Folds the narrator `[LOREBOOK]` block — keyword-matched world facts (ADR 0013).
 *
 * Keyed `LOREBOOK_NARRATOR` so it can coexist with the NPC `LOREBOOK` row
 * (PH-25). Reuses the canonical {@see LorebookKeywordMatcher} (PH-31) so play
 * matches exactly what the author preview shows. The narrator is omniscient, so
 * no `knowledge_boundary` clamp applies (leak rule: none) — but the per-entry
 * minimum-reveal-chapter gate still holds: a fact not yet revealed at the
 * current chapter is withheld. Omitted entirely when nothing matches.
 */
final class LorebookProducer implements BlockProducer
{
    public function __construct(
        private readonly LorebookKeywordMatcher $matcher,
    ) {}

    public function blockKey(): string
    {
        return 'LOREBOOK_NARRATOR';
    }

    public function produce(NarratorContext $context): ?string
    {
        $sample = $context->sampleText();

        if (trim($sample) === '') {
            return null;
        }

        $chapterNumber = $context->currentChapterNumber();
        $lines = [];

        foreach ($context->lorebookEntries as $entry) {
            $matched = $this->matcher->matchedKeywords($entry, $sample);

            if ($matched === [] || $this->matcher->isWithheld($entry, $chapterNumber)) {
                continue;
            }

            $lines[] = '- '.$this->label($entry, $matched).': '.$entry->content;
        }

        return $lines === [] ? null : implode("\n", $lines);
    }

    /**
     * A short heading for an entry: its title, else its matched keywords.
     *
     * @param  list<string>  $matched  The keywords that triggered this entry.
     */
    private function label(LorebookEntry $entry, array $matched): string
    {
        if ($entry->title !== null && trim($entry->title) !== '') {
            return $entry->title;
        }

        return implode(', ', $matched);
    }
}
