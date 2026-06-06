<?php

namespace App\Services;

use App\Models\LorebookEntry;
use Illuminate\Support\Str;

/**
 * Canonical lorebook keyword matching (S-3.2.1, ADR 0013 §5).
 *
 * Powers the author-facing keyword match preview and is the single source of
 * truth for "does this text trigger this entry?". Runtime injection (PH-31,
 * narrator/NPC loop) reuses {@see matchedKeywords()} and {@see isWithheld()} so
 * the preview always mirrors what play will actually inject.
 *
 * Matching is a soft keyword mechanism: a case-insensitive substring containment
 * test, which naturally handles multi-word keywords ("Crystal Hollow"). The only
 * gate it honours is the per-entry minimum-reveal-chapter clamp.
 */
class LorebookKeywordMatcher
{
    /**
     * The entry's keywords that appear in the sample text.
     *
     * Case-insensitive substring match (so "gloves" matches "suppressor gloves"
     * and "Crystal Hollow" matches as a phrase). This is the canonical match
     * runtime injection reuses.
     *
     * @param  LorebookEntry  $entry  The entry whose keywords to test.
     * @param  string  $sampleText  The scene/excerpt text to match against.
     * @return list<string> The matched keywords, in their authored order.
     */
    public function matchedKeywords(LorebookEntry $entry, string $sampleText): array
    {
        if (trim($sampleText) === '') {
            return [];
        }

        $haystack = Str::lower($sampleText);

        return collect($entry->keywords)
            ->filter(function (string $keyword) use ($haystack): bool {
                $needle = Str::lower(trim($keyword));

                return $needle !== '' && str_contains($haystack, $needle);
            })
            ->values()
            ->all();
    }

    /**
     * Whether the entry is withheld at the previewed chapter by its reveal gate.
     *
     * An entry is withheld only when a chapter is being previewed and its
     * minimum-reveal-chapter is later than that chapter. With no previewed
     * chapter the reveal gate is not applied (pure keyword tuning).
     *
     * @param  LorebookEntry  $entry  The entry to test (with `minRevealChapter` loaded).
     * @param  int|null  $previewChapterNumber  The chapter being previewed, or null.
     */
    public function isWithheld(LorebookEntry $entry, ?int $previewChapterNumber): bool
    {
        $revealNumber = $entry->minRevealChapter?->number;

        if ($revealNumber === null || $previewChapterNumber === null) {
            return false;
        }

        return $revealNumber > $previewChapterNumber;
    }

    /**
     * Build the preview result for a sample text at an optional previewed chapter.
     *
     * Entries with no matching keyword are excluded entirely. A matching entry
     * is reported as `triggered` unless its reveal gate withholds it at the
     * previewed chapter, in which case it is reported as `withheld`.
     *
     * @param  iterable<LorebookEntry>  $entries  This story's entries (with `minRevealChapter` loaded).
     * @param  string  $sampleText  The scene/excerpt text to match against.
     * @param  int|null  $previewChapterNumber  The chapter being previewed, or null for no clamp.
     * @return array{triggered: list<array{id: int, title: string|null, keywords: list<string>, matchedKeywords: list<string>}>, withheld: list<array{id: int, title: string|null, keywords: list<string>, matchedKeywords: list<string>, minRevealChapter: array{id: int, number: int, title: string}|null}>}
     */
    public function preview(iterable $entries, string $sampleText, ?int $previewChapterNumber): array
    {
        $triggered = [];
        $withheld = [];

        foreach ($entries as $entry) {
            $matched = $this->matchedKeywords($entry, $sampleText);

            if ($matched === []) {
                continue;
            }

            $row = [
                'id' => $entry->id,
                'title' => $entry->title,
                'keywords' => $entry->keywords,
                'matchedKeywords' => $matched,
            ];

            if ($this->isWithheld($entry, $previewChapterNumber)) {
                $reveal = $entry->minRevealChapter;

                $withheld[] = [
                    ...$row,
                    'minRevealChapter' => $reveal === null ? null : [
                        'id' => $reveal->id,
                        'number' => $reveal->number,
                        'title' => $reveal->title,
                    ],
                ];

                continue;
            }

            $triggered[] = $row;
        }

        return ['triggered' => $triggered, 'withheld' => $withheld];
    }
}
