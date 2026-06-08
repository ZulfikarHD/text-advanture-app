<?php

namespace App\Http\Controllers\Stories;

use App\Enums\StateNode;
use App\Exceptions\Llm\LlmCallFailedException;
use App\Exceptions\Llm\UnresolvedModelRoleException;
use App\Exceptions\Sessions\IllegalLoopTransitionException;
use App\Exceptions\Sessions\StoryNotPlayableException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Stories\RenameSessionRequest;
use App\Http\Requests\Stories\StartSessionRequest;
use App\Http\Requests\Stories\SubmitPlayerInputRequest;
use App\Models\Chapter;
use App\Models\Character;
use App\Models\Event;
use App\Models\LorebookEntry;
use App\Models\PlaySession;
use App\Models\Story;
use App\Services\BeatSequence;
use App\Services\Narrator\NarratorTurnService;
use App\Services\SceneLogService;
use App\Services\SessionService;
use App\Services\SessionStateMachine;
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
        private readonly SceneLogService $sceneLog,
        private readonly SessionStateMachine $stateMachine,
        private readonly BeatSequence $beats,
    ) {}

    /**
     * Enter a book's playthrough — the chapter-first front door (E0.2.2).
     *
     * The fork stays invisible: this resumes the most-recent playthrough or
     * silently forks a fresh one, then lands the player on the Writing/Play page.
     * A not-play-ready story (with no save to resume) is routed back to the
     * overview with an error toast rather than an exception page.
     */
    public function enter(Story $story): RedirectResponse
    {
        Gate::authorize('update', $story);

        try {
            $session = $this->sessions->enter($story);
        } catch (StoryNotPlayableException) {
            return $this->notPlayable($story);
        }

        return to_route('stories.saves.play', [$story, $session]);
    }

    /**
     * Enter a specific chapter — start (or resume) play positioned there (E0.2).
     *
     * Selecting a chapter resumes an in-progress playthrough where it left off,
     * or starts a fresh one at that chapter's opening beat. The fork mechanics
     * stay hidden behind the chapter spine; see {@see SessionService::enter()}.
     */
    public function enterChapter(Story $story, Chapter $chapter): RedirectResponse
    {
        Gate::authorize('update', $story);

        try {
            $session = $this->sessions->enter($story, $chapter);
        } catch (StoryNotPlayableException) {
            return $this->notPlayable($story);
        }

        return to_route('stories.saves.play', [$story, $session]);
    }

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
            'timeline' => $this->presentTimeline($playSession),
            'codex' => $this->presentCodex($story),
            'flow' => $this->presentFlow($playSession),
        ]);
    }

    /**
     * Commit the player's contribution at a player moment (S-5.1.1).
     *
     * Delegates to {@see SessionService::recordPlayerMoment()}, which hands the
     * turn back to the narrator and appends the input to the scene log in one
     * atomic transaction so the next narrator turn — and the readable scrollback
     * — can see it. Acting off-turn is surfaced as an error toast with the save
     * left exactly unchanged (the transaction rolls back).
     */
    public function input(SubmitPlayerInputRequest $request, Story $story, PlaySession $playSession): RedirectResponse
    {
        Gate::authorize('update', $story);

        try {
            $this->sessions->recordPlayerMoment($playSession, $request->validated()['content']);
        } catch (IllegalLoopTransitionException) {
            return $this->backToPlay($story, $playSession, 'error', __('It is not your turn to act right now.'));
        }

        return $this->backToPlay($story, $playSession, 'success', __('Your turn is in — the narrator takes it from here.'));
    }

    /**
     * Close a finished beat and resume at the next one (S-3.1.2).
     *
     * The player's "continue" at a beat boundary: the state machine advances to
     * the next beat in document order, or holds at the end of the story when none
     * remains. Continuing off a beat boundary is surfaced as an error toast.
     */
    public function continueBeat(Story $story, PlaySession $playSession): RedirectResponse
    {
        Gate::authorize('update', $story);

        try {
            $this->stateMachine->completeBeat($playSession);
        } catch (IllegalLoopTransitionException) {
            return $this->backToPlay($story, $playSession, 'error', __('There is no beat to continue from right now.'));
        }

        if ($playSession->state_node === StateNode::BeatComplete) {
            return $this->backToPlay($story, $playSession, 'success', __('You have reached the end of the story.'));
        }

        return $this->backToPlay($story, $playSession, 'success', __('On to the next beat.'));
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

        // Capture the narrated beat before the turn so the scene-log entry is
        // anchored to the beat the prose was written for, not whatever the spine
        // advances to afterward.
        $beatId = $playSession->current_beat_id;

        try {
            $result = $this->narratorTurns->run($playSession);
        } catch (IllegalLoopTransitionException) {
            return $this->backToPlay($story, $playSession, 'error', __("It is not the narrator's turn right now."));
        } catch (UnresolvedModelRoleException) {
            return $this->backToPlay($story, $playSession, 'error', __('No narrator model is configured yet — set one under Settings → Model roles first.'));
        } catch (LlmCallFailedException) {
            return $this->backToPlay($story, $playSession, 'error', __('The narrator was interrupted and its turn could not be read. Your save is unchanged — try again.'));
        }

        $this->sceneLog->recordNarration($playSession, $result, $beatId);

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
     * Flash a not-play-ready toast and route back to the story overview.
     *
     * The chapter-first front door has no save to fall back on, so an
     * unfinished story lands on its overview to finish the requirements rather
     * than on a dead Writing page.
     */
    private function notPlayable(Story $story): RedirectResponse
    {
        Inertia::flash('toast', [
            'type' => 'error',
            'message' => __('This story is not play-ready yet — finish the requirements on its overview first.'),
        ]);

        return to_route('stories.show', $story);
    }

    /**
     * Shape the save's scene log for the readable scrollback (S-5.4.1).
     *
     * @param  PlaySession  $save  The save whose timeline is rendered.
     * @return list<array{id: int, type: string, content: string, speaker: string|null, createdAt: string|null}>
     */
    private function presentTimeline(PlaySession $save): array
    {
        return $save->events()
            ->with('character:id,name')
            ->orderBy('id')
            ->get()
            ->map(fn (Event $event): array => [
                'id' => $event->id,
                'type' => $event->type->value,
                'content' => $event->content,
                'speaker' => $event->character?->name,
                'createdAt' => $event->created_at?->toIso8601String(),
            ])
            ->all();
    }

    /**
     * Shape the story's cast + world facts for the Writing page's codex rail.
     *
     * Read-only references the player can glance at while playing; never exposes
     * a character's private interiority (only name + slug), keeping the rail safe
     * for a player surface.
     *
     * @param  Story  $story  The story being played.
     * @return array{characters: list<array{id: int, name: string, slug: string, isPlayer: bool}>, lore: list<array{id: int, title: string|null}>}
     */
    private function presentCodex(Story $story): array
    {
        return [
            'characters' => $story->characters()
                ->orderByDesc('is_player')
                ->orderBy('name')
                ->get(['id', 'name', 'slug', 'is_player'])
                ->map(fn (Character $character): array => [
                    'id' => $character->id,
                    'name' => $character->name,
                    'slug' => $character->slug,
                    'isPlayer' => $character->is_player,
                ])
                ->all(),
            'lore' => $story->lorebookEntries()
                ->orderBy('id')
                ->get(['id', 'title'])
                ->map(fn (LorebookEntry $entry): array => [
                    'id' => $entry->id,
                    'title' => $entry->title,
                ])
                ->all(),
        ];
    }

    /**
     * Derive whose turn it is so the Writing page shows the right one control.
     *
     * Maps the loop node to the single next action: the narrator may advance, the
     * player may write, the player may continue past a beat boundary, or the
     * story has ended (a beat boundary with no next beat in document order).
     *
     * @param  PlaySession  $save  The save whose loop position is read.
     * @return array{state: string, awaitingNarrator: bool, awaitingPlayer: bool, atBeatBoundary: bool, ended: bool}
     */
    private function presentFlow(PlaySession $save): array
    {
        $state = $save->state_node;
        $atBeatComplete = $state === StateNode::BeatComplete;

        $hasNextBeat = $atBeatComplete
            && $save->current_beat_id !== null
            && $this->beats->next($save->currentBeat()->firstOrFail()) !== null;

        return [
            'state' => $state->value,
            'awaitingNarrator' => in_array($state, [StateNode::SessionStart, StateNode::NarratorTurn], true),
            'awaitingPlayer' => $state === StateNode::PlayerMoment,
            'atBeatBoundary' => $atBeatComplete && $hasNextBeat,
            'ended' => $atBeatComplete && ! $hasNextBeat,
        ];
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
