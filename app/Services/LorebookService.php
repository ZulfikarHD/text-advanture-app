<?php

namespace App\Services;

use App\Models\LorebookEntry;
use App\Models\Story;
use Illuminate\Support\Facades\DB;

/**
 * Lorebook entry lifecycle management (S-3.1.1, ADR 0013 §5).
 *
 * Handles create / update / delete as atomic, story-scoped operations. Keyword
 * normalisation (trim, drop empties, de-dupe) is centralised here so every
 * caller stores the same clean shape the runtime keyword match relies on.
 */
class LorebookService
{
    /**
     * Create a lorebook entry under the given story.
     *
     * @param  Story  $story  The owner-scoped parent story.
     * @param  array{title?: string|null, keywords: list<string>, content: string, min_reveal_chapter_id?: int|null}  $data
     */
    public function create(Story $story, array $data): LorebookEntry
    {
        return DB::transaction(fn (): LorebookEntry => $story->lorebookEntries()->create([
            'title' => $data['title'] ?? null,
            'keywords' => $this->normalizeKeywords($data['keywords']),
            'content' => $data['content'],
            'min_reveal_chapter_id' => $data['min_reveal_chapter_id'] ?? null,
        ]));
    }

    /**
     * Update an existing lorebook entry.
     *
     * @param  LorebookEntry  $entry  The entry to update (already scope-authorized).
     * @param  array{title?: string|null, keywords: list<string>, content: string, min_reveal_chapter_id?: int|null}  $data
     */
    public function update(LorebookEntry $entry, array $data): LorebookEntry
    {
        return DB::transaction(function () use ($entry, $data): LorebookEntry {
            $entry->update([
                'title' => $data['title'] ?? null,
                'keywords' => $this->normalizeKeywords($data['keywords']),
                'content' => $data['content'],
                'min_reveal_chapter_id' => $data['min_reveal_chapter_id'] ?? null,
            ]);

            return $entry->refresh();
        });
    }

    /**
     * Delete a lorebook entry.
     *
     * @param  LorebookEntry  $entry  The entry to remove (already scope-authorized).
     */
    public function delete(LorebookEntry $entry): void
    {
        DB::transaction(fn () => $entry->delete());
    }

    /**
     * Trim, drop empties, and de-duplicate authored keywords.
     *
     * Runtime injection matches the active excerpt against these strings, so a
     * clean list (no blanks, no dupes) keeps the match cheap and predictable.
     *
     * @param  list<string>  $keywords  The raw authored keywords.
     * @return list<string> The normalised keyword list.
     */
    private function normalizeKeywords(array $keywords): array
    {
        return collect($keywords)
            ->map(fn (string $keyword): string => trim($keyword))
            ->filter(fn (string $keyword): bool => $keyword !== '')
            ->unique()
            ->values()
            ->all();
    }
}
