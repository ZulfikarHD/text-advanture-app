<?php

namespace App\Http\Controllers\Stories;

use App\Http\Controllers\Controller;
use App\Http\Requests\Stories\StoreRevealLedgerEntryRequest;
use App\Http\Requests\Stories\UpdateRevealLedgerEntryRequest;
use App\Models\Chapter;
use App\Models\Character;
use App\Models\RevealLedger;
use App\Models\Story;
use App\Services\RevealLedgerService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Reveal-ledger entry CRUD for a story's workspace (S-4.1.1, ADR 0013 §3).
 *
 * Reveal-ledger entries are story-scoped load-bearing secrets `{ fact,
 * reveal_chapter, character?, who_knows[] }` that make spoiler-safety explicit
 * rather than inferred — the compile clamp later excludes a fact from any card
 * before its reveal chapter. The parent `{story:slug}` binds under the
 * `OwnerScope`, so a foreign story resolves to 404; the child
 * `{revealLedgerEntry}` is resolved by scoped route-model binding (it must
 * belong to the bound story). Authorization is on the parent story — entries
 * inherit isolation transitively through it, so there is no entry-level policy.
 * Writes go through {@see RevealLedgerService} for atomic, transactional
 * operations.
 */
class RevealLedgerController extends Controller
{
    public function __construct(private readonly RevealLedgerService $revealLedger) {}

    /**
     * Render the story's reveal ledger with its entries, chapters, and cast.
     */
    public function index(Story $story): Response
    {
        Gate::authorize('view', $story);

        $entries = $story->revealLedgerEntries()
            ->with(['revealChapter:id,number,title', 'character:id,slug,name'])
            ->orderBy('fact')
            ->orderByDesc('id')
            ->get()
            ->map(fn (RevealLedger $entry): array => [
                'id' => $entry->id,
                'fact' => $entry->fact,
                'character' => $entry->character === null ? null : [
                    'id' => $entry->character->id,
                    'slug' => $entry->character->slug,
                    'name' => $entry->character->name,
                ],
                'revealChapter' => [
                    'id' => $entry->revealChapter->id,
                    'number' => $entry->revealChapter->number,
                    'title' => $entry->revealChapter->title,
                ],
                'whoKnows' => $entry->who_knows,
                'notes' => $entry->notes,
            ]);

        return Inertia::render('stories/RevealLedger', [
            'story' => [
                'id' => $story->id,
                'slug' => $story->slug,
                'title' => $story->title,
            ],
            'entries' => $entries,
            'chapters' => $this->presentChapters($story),
            'characters' => $this->presentCharacters($story),
        ]);
    }

    /**
     * Create a reveal-ledger entry from the workspace dialog.
     */
    public function store(StoreRevealLedgerEntryRequest $request, Story $story): RedirectResponse
    {
        Gate::authorize('update', $story);

        $this->revealLedger->create($story, $request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Reveal-ledger entry created.')]);

        return to_route('stories.reveal-ledger.index', $story);
    }

    /**
     * Persist edits to an existing reveal-ledger entry.
     */
    public function update(UpdateRevealLedgerEntryRequest $request, Story $story, RevealLedger $revealLedgerEntry): RedirectResponse
    {
        Gate::authorize('update', $story);

        $this->revealLedger->update($revealLedgerEntry, $request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Reveal-ledger entry updated.')]);

        return to_route('stories.reveal-ledger.index', $story);
    }

    /**
     * Remove a reveal-ledger entry.
     */
    public function destroy(Story $story, RevealLedger $revealLedgerEntry): RedirectResponse
    {
        Gate::authorize('update', $story);

        $this->revealLedger->delete($revealLedgerEntry);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Reveal-ledger entry deleted.')]);

        return to_route('stories.reveal-ledger.index', $story);
    }

    /**
     * Build the chapter options for the required reveal-chapter selector.
     *
     * Chapters land in a later phase, so this is usually empty today; the UI
     * gates entry creation behind a teaching empty state when there are none.
     *
     * @param  Story  $story  The story whose chapters anchor the reveal point.
     * @return list<array{id: int, number: int, title: string}>
     */
    private function presentChapters(Story $story): array
    {
        return $story->chapters()
            ->orderBy('number')
            ->get(['id', 'number', 'title'])
            ->map(fn (Chapter $chapter): array => [
                'id' => $chapter->id,
                'number' => $chapter->number,
                'title' => $chapter->title,
            ])
            ->all();
    }

    /**
     * Build the character options for the optional "about" selector.
     *
     * Characters land in a later phase, so this is usually empty today; the UI
     * degrades to a world-secret-only choice when there are none.
     *
     * @param  Story  $story  The story whose cast a secret may be about.
     * @return list<array{id: int, slug: string, name: string}>
     */
    private function presentCharacters(Story $story): array
    {
        return $story->characters()
            ->orderBy('name')
            ->get(['id', 'slug', 'name'])
            ->map(fn (Character $character): array => [
                'id' => $character->id,
                'slug' => $character->slug,
                'name' => $character->name,
            ])
            ->all();
    }
}
