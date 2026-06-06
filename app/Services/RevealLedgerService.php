<?php

namespace App\Services;

use App\Models\RevealLedger;
use App\Models\Story;
use Illuminate\Support\Facades\DB;

/**
 * Reveal-ledger entry lifecycle management (S-4.1.1, ADR 0013 §3).
 *
 * Handles create / update / delete as atomic, story-scoped operations. The
 * `who_knows` slug list is normalised here (trim, drop empties, de-dupe) so
 * every caller stores the same clean shape the compile clamp later reads when
 * exempting pre-reveal knowers.
 */
class RevealLedgerService
{
    /**
     * Create a reveal-ledger entry under the given story.
     *
     * @param  Story  $story  The owner-scoped parent story.
     * @param  array{fact: string, reveal_chapter_id: int, character_id?: int|null, who_knows?: list<string>, notes?: string|null}  $data
     */
    public function create(Story $story, array $data): RevealLedger
    {
        return DB::transaction(fn (): RevealLedger => $story->revealLedgerEntries()->create([
            'fact' => $data['fact'],
            'character_id' => $data['character_id'] ?? null,
            'reveal_chapter_id' => $data['reveal_chapter_id'],
            'who_knows' => $this->normalizeWhoKnows($data['who_knows'] ?? []),
            'notes' => $data['notes'] ?? null,
        ]));
    }

    /**
     * Update an existing reveal-ledger entry.
     *
     * @param  RevealLedger  $entry  The entry to update (already scope-authorized).
     * @param  array{fact: string, reveal_chapter_id: int, character_id?: int|null, who_knows?: list<string>, notes?: string|null}  $data
     */
    public function update(RevealLedger $entry, array $data): RevealLedger
    {
        return DB::transaction(function () use ($entry, $data): RevealLedger {
            $entry->update([
                'fact' => $data['fact'],
                'character_id' => $data['character_id'] ?? null,
                'reveal_chapter_id' => $data['reveal_chapter_id'],
                'who_knows' => $this->normalizeWhoKnows($data['who_knows'] ?? []),
                'notes' => $data['notes'] ?? null,
            ]);

            return $entry->refresh();
        });
    }

    /**
     * Delete a reveal-ledger entry.
     *
     * @param  RevealLedger  $entry  The entry to remove (already scope-authorized).
     */
    public function delete(RevealLedger $entry): void
    {
        DB::transaction(fn () => $entry->delete());
    }

    /**
     * Trim, drop empties, and de-duplicate the authored who-knows slugs.
     *
     * These slugs name the characters exempt from the reveal clamp for this
     * fact, so a clean list (no blanks, no dupes) keeps the later exemption
     * lookup predictable.
     *
     * @param  list<string>  $whoKnows  The raw authored character slugs.
     * @return list<string> The normalised slug list.
     */
    private function normalizeWhoKnows(array $whoKnows): array
    {
        return collect($whoKnows)
            ->map(fn (string $slug): string => trim($slug))
            ->filter(fn (string $slug): bool => $slug !== '')
            ->unique()
            ->values()
            ->all();
    }
}
