<?php

namespace App\Http\Controllers\Stories;

use App\Exceptions\Sessions\StoryNotPlayableException;
use App\Http\Controllers\Controller;
use App\Models\PlaySession;
use App\Models\Story;
use App\Services\SessionService;
use App\Services\StoryOverviewService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Session (save) surface for a story's workspace — start a playthrough (S-2.1.1).
 *
 * Forks a play-ready story into the save realm: the Saves surface lists the
 * playthroughs forked from this story and starts new ones; the Play surface is
 * the reachable next step a fresh save lands on (its full reader ships in
 * S-5.4.1). The parent `{story:slug}` binds under the `OwnerScope`, so a foreign
 * story resolves to 404; the nested `{playSession}` resolves through
 * `Story::playSessions()` via scoped binding, so a save from another story (or
 * owner) resolves to 404 without a row-level policy. Authorization is on the
 * parent story (`view` to read, `update` to fork); forking never mutates the
 * authoring template (ADR 0012). The fork itself runs in {@see SessionService}.
 *
 * Multi-save management (name/list/load/reset/delete, S-2.1.2) and loop-state
 * resume (S-2.1.3) land in later stories; this surface only starts and lists.
 */
class SessionController extends Controller
{
    public function __construct(
        private readonly SessionService $sessions,
        private readonly StoryOverviewService $overview,
    ) {}

    /**
     * List the saves forked from this story and the play-readiness gate.
     */
    public function index(Story $story): Response
    {
        Gate::authorize('view', $story);

        return Inertia::render('stories/Saves', [
            'story' => [
                'id' => $story->id,
                'slug' => $story->slug,
                'title' => $story->title,
            ],
            'readiness' => $this->overview->readiness($story),
            'saves' => $story->playSessions()
                ->with($this->positionEagerLoads())
                ->orderByDesc('last_played_at')
                ->orderByDesc('id')
                ->get()
                ->map(fn (PlaySession $save): array => $this->presentSave($save))
                ->all(),
        ]);
    }

    /**
     * Start a new session by forking the story into the save realm.
     *
     * Re-checks play-readiness server-side (never trusting the client's disabled
     * button) and maps a not-play-ready story to an error toast rather than an
     * exception page, then lands the player on the fresh save's Play surface.
     */
    public function store(Story $story): RedirectResponse
    {
        Gate::authorize('update', $story);

        try {
            $session = $this->sessions->fork($story);
        } catch (StoryNotPlayableException) {
            Inertia::flash('toast', [
                'type' => 'error',
                'message' => __('This story is not play-ready yet — finish the requirements on its overview first.'),
            ]);

            return to_route('stories.saves.index', $story);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Session started.')]);

        return to_route('stories.saves.play', [$story, $session]);
    }

    /**
     * Render a save's Play surface — the reachable next step after a fork.
     *
     * A placeholder this phase: it orients the player at their save's position
     * (state node + current chapter/scene/beat) until the prose reader and loop
     * controls ship in S-5.4.1.
     */
    public function play(Story $story, PlaySession $playSession): Response
    {
        Gate::authorize('view', $story);

        $playSession->load($this->positionEagerLoads());

        return Inertia::render('sessions/Play', [
            'story' => [
                'id' => $story->id,
                'slug' => $story->slug,
                'title' => $story->title,
            ],
            'save' => $this->presentSave($playSession),
        ]);
    }

    /**
     * Eager-load constraints for a save's authoring position.
     *
     * Each string selects only the columns the Saves list / Play surface render,
     * keeping the FK (`id`) the belongs-to relation needs.
     *
     * @return list<string>
     */
    private function positionEagerLoads(): array
    {
        return [
            'currentChapter:id,number,title',
            'currentScene:id,number',
            'currentBeat:id,goal',
        ];
    }

    /**
     * Shape a save for the Saves list and the Play surface.
     *
     * @param  PlaySession  $save  A save with its position relations loaded.
     * @return array{id: int, name: string, stateNode: string, stateLabel: string, lastPlayedAt: string|null, position: array{chapterNumber: int|null, chapterTitle: string|null, sceneNumber: int|null, beatGoal: string|null}}
     */
    private function presentSave(PlaySession $save): array
    {
        return [
            'id' => $save->id,
            'name' => $save->name,
            'stateNode' => $save->state_node->value,
            'stateLabel' => $save->state_node->label(),
            'lastPlayedAt' => $save->last_played_at?->toIso8601String(),
            'position' => [
                'chapterNumber' => $save->currentChapter?->number,
                'chapterTitle' => $save->currentChapter?->title,
                'sceneNumber' => $save->currentScene?->number,
                'beatGoal' => $save->currentBeat?->goal,
            ],
        ];
    }
}
