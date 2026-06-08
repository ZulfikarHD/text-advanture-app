<?php

namespace App\Http\Controllers\Stories;

use App\Http\Controllers\Controller;
use App\Models\Chapter;
use App\Models\Story;
use App\Services\StoryOverviewService;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Story overview — the authoring inventory + play-readiness view (S-1.2.2).
 *
 * The default surface of a story's workspace. Both the counts and the
 * play-readiness gate are derived on read by {@see StoryOverviewService}; the
 * route binds `{story:slug}` under the owner scope, so a foreign story resolves
 * to 404 without leaking its existence.
 */
class StoryOverviewController extends Controller
{
    public function __construct(private readonly StoryOverviewService $overview) {}

    /**
     * Render a story's overview with derived counts and play-readiness.
     */
    public function show(Story $story): Response
    {
        Gate::authorize('view', $story);

        return Inertia::render('stories/Overview', [
            'story' => [
                'id' => $story->id,
                'slug' => $story->slug,
                'title' => $story->title,
                'description' => $story->description,
            ],
            'counts' => $this->overview->counts($story),
            'readiness' => $this->overview->readiness($story),
            'chapters' => $this->presentChapterSpine($story),
        ]);
    }

    /**
     * Shape the story's chapters as the play-entry spine (E0.2).
     *
     * The ordered chapter list the overview renders as the chapter-first entrance:
     * each row links into the Writing/Play page positioned at that chapter. The
     * `playableBeats` count tells the author which chapters can actually be
     * entered yet (a chapter with no beat falls back to the story's first).
     *
     * @param  Story  $story  The story whose chapters anchor the spine.
     * @return list<array{id: int, number: int, title: string, playableBeats: int}>
     */
    private function presentChapterSpine(Story $story): array
    {
        return $story->chapters()
            ->withCount(['scenes as playable_beats' => fn ($query) => $query->join('beats', 'beats.scene_id', '=', 'scenes.id')])
            ->orderBy('number')
            ->get()
            ->map(fn (Chapter $chapter): array => [
                'id' => $chapter->id,
                'number' => $chapter->number,
                'title' => $chapter->title,
                'playableBeats' => (int) $chapter->playable_beats,
            ])
            ->all();
    }
}
