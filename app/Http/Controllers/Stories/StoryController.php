<?php

namespace App\Http\Controllers\Stories;

use App\Http\Controllers\Controller;
use App\Http\Requests\Stories\StoreStoryRequest;
use App\Http\Requests\Stories\UpdateStoryRequest;
use App\Models\Story;
use App\Services\StoryService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Story CRUD for the workspace dashboard (S-1.1.1 / S-1.1.2).
 *
 * All reads are automatically owner-scoped by the `OwnerScope` global scope
 * on {@see Story}. Route-model binding resolves `{story:slug}` under that
 * scope, so a foreign story resolves to 404 without leaking its existence.
 * Writes go through {@see StoryService} for atomic, transactional operations.
 */
class StoryController extends Controller
{
    public function __construct(private readonly StoryService $stories) {}

    /**
     * Render the workspace dashboard with the author's story list.
     */
    public function index(Request $request): Response
    {
        $stories = Story::query()
            ->with(['latestPlaySession' => fn ($query) => $query->with('currentChapter:id,number,title')])
            ->orderByDesc('updated_at')
            ->get()
            ->map(fn (Story $story): array => [
                'id' => $story->id,
                'slug' => $story->slug,
                'title' => $story->title,
                'description' => $story->description,
                'updatedAtForHumans' => $story->updated_at?->diffForHumans(),
                'resume' => $this->presentResume($story),
            ]);

        return Inertia::render('Dashboard', [
            'stories' => $stories,
        ]);
    }

    /**
     * Shape a story's resume hint for the play-first home (E0.1).
     *
     * Null when the story has never been played; otherwise the position and
     * recency the "Continue" CTA surfaces so a book card invites resuming where
     * the player left off.
     *
     * @param  Story  $story  The story with its `latestPlaySession` eager-loaded.
     * @return array{chapterNumber: int|null, chapterTitle: string|null, lastPlayedForHumans: string|null}|null
     */
    private function presentResume(Story $story): ?array
    {
        $save = $story->latestPlaySession;

        if ($save === null) {
            return null;
        }

        return [
            'chapterNumber' => $save->currentChapter?->number,
            'chapterTitle' => $save->currentChapter?->title,
            'lastPlayedForHumans' => $save->last_played_at?->diffForHumans(),
        ];
    }

    /**
     * Create a new story from the workspace dialog.
     */
    public function store(StoreStoryRequest $request): RedirectResponse
    {
        Gate::authorize('create', Story::class);

        $this->stories->create($request->user(), $request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Story created.')]);

        return to_route('dashboard');
    }

    /**
     * Render the story edit page.
     */
    public function edit(Story $story): Response
    {
        Gate::authorize('view', $story);

        return Inertia::render('stories/Edit', [
            'story' => [
                'id' => $story->id,
                'slug' => $story->slug,
                'title' => $story->title,
                'description' => $story->description,
            ],
        ]);
    }

    /**
     * Persist edits to an existing story.
     */
    public function update(UpdateStoryRequest $request, Story $story): RedirectResponse
    {
        Gate::authorize('update', $story);

        $this->stories->update($story, $request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Story updated.')]);

        return to_route('stories.edit', $story->fresh());
    }

    /**
     * Remove a story and cascade its authoring children.
     */
    public function destroy(Request $request, Story $story): RedirectResponse
    {
        Gate::authorize('delete', $story);

        $this->stories->delete($story);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Story deleted.')]);

        return to_route('dashboard');
    }
}
