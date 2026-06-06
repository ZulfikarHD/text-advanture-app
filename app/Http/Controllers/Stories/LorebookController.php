<?php

namespace App\Http\Controllers\Stories;

use App\Http\Controllers\Controller;
use App\Http\Requests\Stories\PreviewLorebookRequest;
use App\Http\Requests\Stories\StoreLorebookEntryRequest;
use App\Http\Requests\Stories\UpdateLorebookEntryRequest;
use App\Models\Chapter;
use App\Models\LorebookEntry;
use App\Models\Story;
use App\Services\LorebookKeywordMatcher;
use App\Services\LorebookService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Lorebook entry CRUD for a story's workspace (S-3.1.1, ADR 0013 §5).
 *
 * Lorebook entries are story-scoped world facts injected on keyword match at
 * runtime. The parent `{story:slug}` binds under the `OwnerScope`, so a foreign
 * story resolves to 404; the child `{lorebookEntry}` is resolved by scoped
 * route-model binding (it must belong to the bound story). Authorization is on
 * the parent story — entries inherit isolation transitively through it, so there
 * is no entry-level policy. Writes go through {@see LorebookService} for atomic,
 * transactional operations.
 */
class LorebookController extends Controller
{
    public function __construct(
        private readonly LorebookService $lorebook,
        private readonly LorebookKeywordMatcher $matcher,
    ) {}

    /**
     * Render the story's lorebook with its entries and chapter options.
     */
    public function index(Story $story): Response
    {
        Gate::authorize('view', $story);

        $entries = $story->lorebookEntries()
            ->with('minRevealChapter:id,number,title')
            ->orderBy('title')
            ->orderByDesc('id')
            ->get()
            ->map(fn (LorebookEntry $entry): array => [
                'id' => $entry->id,
                'title' => $entry->title,
                'keywords' => $entry->keywords,
                'content' => $entry->content,
                'minRevealChapter' => $entry->minRevealChapter === null ? null : [
                    'id' => $entry->minRevealChapter->id,
                    'number' => $entry->minRevealChapter->number,
                    'title' => $entry->minRevealChapter->title,
                ],
            ]);

        return Inertia::render('stories/Lorebook', [
            'story' => [
                'id' => $story->id,
                'slug' => $story->slug,
                'title' => $story->title,
            ],
            'entries' => $entries,
            'chapters' => $this->presentChapters($story),
        ]);
    }

    /**
     * Create a lorebook entry from the workspace dialog.
     */
    public function store(StoreLorebookEntryRequest $request, Story $story): RedirectResponse
    {
        Gate::authorize('update', $story);

        $this->lorebook->create($story, $request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Lorebook entry created.')]);

        return to_route('stories.lorebook.index', $story);
    }

    /**
     * Persist edits to an existing lorebook entry.
     */
    public function update(UpdateLorebookEntryRequest $request, Story $story, LorebookEntry $lorebookEntry): RedirectResponse
    {
        Gate::authorize('update', $story);

        $this->lorebook->update($lorebookEntry, $request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Lorebook entry updated.')]);

        return to_route('stories.lorebook.index', $story);
    }

    /**
     * Remove a lorebook entry.
     */
    public function destroy(Story $story, LorebookEntry $lorebookEntry): RedirectResponse
    {
        Gate::authorize('update', $story);

        $this->lorebook->delete($lorebookEntry);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Lorebook entry deleted.')]);

        return to_route('stories.lorebook.index', $story);
    }

    /**
     * Preview which entries a sample text would trigger (S-3.2.1, ADR 0013 §5).
     *
     * Answers a standalone client request (Inertia `useHttp`) with the entries
     * the sample text matches, split into `triggered` and (reveal-gate)
     * `withheld`. Read-only and side-effect-free; it uses the same matching as
     * runtime injection so the author can tune keywords before play.
     *
     * @return JsonResponse The `{ triggered, withheld }` match result (no secrets).
     */
    public function preview(PreviewLorebookRequest $request, Story $story): JsonResponse
    {
        Gate::authorize('view', $story);

        $validated = $request->validated();

        $previewChapterNumber = null;

        if (! empty($validated['chapter_id'])) {
            $previewChapterNumber = $story->chapters()
                ->whereKey($validated['chapter_id'])
                ->value('number');
        }

        $entries = $story->lorebookEntries()
            ->with('minRevealChapter:id,number,title')
            ->orderBy('title')
            ->orderByDesc('id')
            ->get();

        return response()->json(
            $this->matcher->preview($entries, $validated['sample_text'], $previewChapterNumber),
        );
    }

    /**
     * Build the chapter options for the optional reveal-chapter selector.
     *
     * Chapters land in a later phase, so this is usually empty today; the UI
     * degrades to a disabled selector with a hint when there are none.
     *
     * @param  Story  $story  The story whose chapters gate reveals.
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
}
