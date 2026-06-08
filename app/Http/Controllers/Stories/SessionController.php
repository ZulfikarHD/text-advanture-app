<?php

namespace App\Http\Controllers\Stories;

use App\Exceptions\Llm\LlmCallFailedException;
use App\Exceptions\Llm\UnresolvedModelRoleException;
use App\Exceptions\Sessions\IllegalLoopTransitionException;
use App\Exceptions\Sessions\StoryNotPlayableException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Stories\RenameSessionRequest;
use App\Http\Requests\Stories\StartSessionRequest;
use App\Models\PlaySession;
use App\Models\Story;
use App\Services\Narrator\NarratorTurnService;
use App\Services\SessionService;
use App\Services\StoryOverviewService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Session (save) surface for a story's workspace — start and manage saves.
 *
 * The Saves surface forks a play-ready story into the save realm and manages the
 * parallel, independent playthroughs forked from it:
 *
 * - **start** (S-2.1.1): fork the play-ready story into a new save.
 * - **rename / reset / delete** (S-2.1.2): manage saves without touching
 *   siblings or the authoring template.
 * - **load → resume** (S-2.1.3): opening a save's Play surface stamps it as
 *   most-recently-played and restores its persisted loop position (the full
 *   prose reader ships in S-5.4.1).
 *
 * The parent `{story:slug}` binds under the `OwnerScope`, so a foreign story
 * resolves to 404; the nested `{playSession}` resolves through
 * `Story::playSessions()` via scoped binding, so a save from another story (or
 * owner) resolves to 404 without a row-level policy. Authorization is on the
 * parent story (`view` to read, `update` to fork/rename/reset/delete); forking
 * and play never mutate the authoring template (ADR 0012). The save lifecycle
 * itself runs in {@see SessionService}.
 */
class SessionController extends Controller
{
    public function __construct(
        private readonly SessionService $sessions,
        private readonly StoryOverviewService $overview,
        private readonly NarratorTurnService $narratorTurns,
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
     * exception page, then lands the player on the fresh save's Play surface. An
     * optional author-supplied name is accepted; otherwise the service derives
     * "Playthrough N".
     */
    public function store(StartSessionRequest $request, Story $story): RedirectResponse
    {
        Gate::authorize('update', $story);

        try {
            $session = $this->sessions->fork($story, $request->validated()['name'] ?? null);
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
     * Rename a save (S-2.1.2).
     */
    public function update(RenameSessionRequest $request, Story $story, PlaySession $playSession): RedirectResponse
    {
        Gate::authorize('update', $story);

        $this->sessions->rename($playSession, $request->validated()['name']);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Save renamed.')]);

        return to_route('stories.saves.index', $story);
    }

    /**
     * Reset a save back to its freshly-forked state (S-2.1.2).
     */
    public function reset(Story $story, PlaySession $playSession): RedirectResponse
    {
        Gate::authorize('update', $story);

        $this->sessions->reset($playSession);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Save reset to its starting position.')]);

        return to_route('stories.saves.index', $story);
    }

    /**
     * Delete a save (S-2.1.2).
     */
    public function destroy(Story $story, PlaySession $playSession): RedirectResponse
    {
        Gate::authorize('update', $story);

        $this->sessions->delete($playSession);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Save deleted.')]);

        return to_route('stories.saves.index', $story);
    }

    /**
     * Render a save's Play surface — resuming at its persisted loop position.
     *
     * Loading a save *is* resuming it (S-2.1.3): the persisted loop state
     * already lives on the row, so this stamps it as most-recently-played and
     * renders it at exactly where it left off. A placeholder body this phase: it
     * orients the player at their save's position until the prose reader and loop
     * controls ship in S-5.4.1.
     */
    public function play(Story $story, PlaySession $playSession): Response
    {
        Gate::authorize('view', $story);

        $this->sessions->resume($playSession);

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
     * Run one narrator turn and advance the save's loop (S-4.2.1 / S-4.2.2).
     *
     * Delegates the prose call to {@see NarratorTurnService}: a validated result
     * advances the spine, while a malformed or failed call is surfaced as an
     * error toast with the save left exactly as it was — the loop never trusts an
     * unparseable result, and the player can retry without losing the session
     * (the prose reader + advance control land in S-5.4.1). Either way the player
     * is returned to the Play surface at the save's (un)changed position.
     */
    public function narrate(Story $story, PlaySession $playSession): RedirectResponse
    {
        Gate::authorize('update', $story);

        try {
            $this->narratorTurns->run($playSession);
        } catch (IllegalLoopTransitionException) {
            return $this->backToPlay($story, $playSession, 'error', __("It is not the narrator's turn right now."));
        } catch (UnresolvedModelRoleException) {
            return $this->backToPlay($story, $playSession, 'error', __('No narrator model is configured yet — set one under Settings → Model roles first.'));
        } catch (LlmCallFailedException) {
            return $this->backToPlay($story, $playSession, 'error', __('The narrator was interrupted and its turn could not be read. Your save is unchanged — try again.'));
        }

        return $this->backToPlay($story, $playSession, 'success', __('The narrator advanced the scene.'));
    }

    /**
     * Flash a toast and return the player to the save's Play surface.
     *
     * @param  string  $type  The toast variant (`success` | `error`).
     * @param  string  $message  The human-readable toast message.
     */
    private function backToPlay(Story $story, PlaySession $playSession, string $type, string $message): RedirectResponse
    {
        Inertia::flash('toast', ['type' => $type, 'message' => $message]);

        return to_route('stories.saves.play', [$story, $playSession]);
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
     * `resumeAnchor` is the persisted narrator continuity seam; it stays null
     * until a narrator turn writes it (S-5.3.1), letting the Play surface show
     * "resuming" versus "starting fresh".
     *
     * @param  PlaySession  $save  A save with its position relations loaded.
     * @return array{id: int, name: string, stateNode: string, stateLabel: string, lastPlayedAt: string|null, resumeAnchor: array<string, mixed>|null, position: array{chapterNumber: int|null, chapterTitle: string|null, sceneNumber: int|null, beatGoal: string|null}}
     */
    private function presentSave(PlaySession $save): array
    {
        return [
            'id' => $save->id,
            'name' => $save->name,
            'stateNode' => $save->state_node->value,
            'stateLabel' => $save->state_node->label(),
            'lastPlayedAt' => $save->last_played_at?->toIso8601String(),
            'resumeAnchor' => $save->resume_anchor,
            'position' => [
                'chapterNumber' => $save->currentChapter?->number,
                'chapterTitle' => $save->currentChapter?->title,
                'sceneNumber' => $save->currentScene?->number,
                'beatGoal' => $save->currentBeat?->goal,
            ],
        ];
    }
}
