<?php

namespace App\Services;

use App\Enums\Handoff;
use App\Enums\StateNode;
use App\Exceptions\Sessions\IllegalLoopTransitionException;
use App\Models\Beat;
use App\Models\PlaySession;

/**
 * The narrator-loop spine — the session's only conductor (S-3.1.1 / S-3.1.2,
 * ADR 0016 §1/§5).
 *
 * Drives a save deterministically through
 * `session_start -> narrator_turn -> { player_moment | beat_complete } ->
 * narrator_resumes`, persisting the position on `play_sessions` as it goes.
 * There is no separate orchestrator: this one service owns the whole transition
 * table, and the only branch input is the narrator turn's structured
 * {@see Handoff} signal (produced by the prose call in S-4.2.1 and passed into
 * {@see applyHandoff()} — injected here so the spine is testable without an LLM).
 *
 * `narrator_resumes` is the edge back to `narrator_turn` after a pause, not its
 * own node, so it is expressed by {@see resumeFromPlayerMoment()} (after the
 * player acts) and {@see completeBeat()} (after a beat closes).
 *
 * Scope this phase: only `state_node` and the `current_*` position move. The
 * `resume_anchor` content (S-5.3.1), word/nudge clocks (Phase 4), the immediate
 * context `events` (S-5.2.1), and the `npc_moment` branch (Phase 2) are
 * deliberately untouched — each producer plugs into this spine without
 * reshaping it (PH-37).
 */
class SessionStateMachine
{
    public function __construct(private readonly BeatSequence $beats) {}

    /**
     * Enter the loop: `session_start -> narrator_turn`.
     *
     * The first transition off the forked entry node, handing control to the
     * narrator for its opening turn.
     *
     * @param  PlaySession  $session  The save to start (positioned at its first beat by the fork).
     * @return PlaySession The same save, now on `narrator_turn`.
     *
     * @throws IllegalLoopTransitionException When the save is not on `session_start`.
     */
    public function begin(PlaySession $session): PlaySession
    {
        $this->guard($session, StateNode::SessionStart, 'begin');

        return $this->moveTo($session, StateNode::NarratorTurn);
    }

    /**
     * Route a completed narrator turn by its handoff signal:
     * `narrator_turn -> player_moment | beat_complete`.
     *
     * The handoff is the narrator turn's own structured output, so the next node
     * is fully determined by it — no separate classifier pass. `npc_moment` is a
     * valid registry handoff but its branch is not wired until Phase 2, so it is
     * rejected here rather than routed.
     *
     * @param  PlaySession  $session  The save whose narrator turn just completed.
     * @param  Handoff  $handoff  The narrator turn's structured handoff signal.
     * @return PlaySession The same save, now on `player_moment` or `beat_complete`.
     *
     * @throws IllegalLoopTransitionException When the save is not on `narrator_turn`, or the handoff is `npc_moment`.
     */
    public function applyHandoff(PlaySession $session, Handoff $handoff): PlaySession
    {
        $this->guard($session, StateNode::NarratorTurn, 'applyHandoff');

        return match ($handoff) {
            Handoff::PlayerMoment => $this->moveTo($session, StateNode::PlayerMoment),
            Handoff::BeatComplete => $this->moveTo($session, StateNode::BeatComplete),
            Handoff::NpcMoment => throw IllegalLoopTransitionException::npcMomentNotReachable(),
        };
    }

    /**
     * Resume after the player has acted: `player_moment -> narrator_turn`.
     *
     * This is the `narrator_resumes` edge for a player moment. The save stays on
     * the same beat — the narrator continues from the player's contribution
     * rather than restarting the beat. Committing the player's input itself is
     * S-5.1.1; this only advances the loop node.
     *
     * @param  PlaySession  $session  The save awaiting the player at a player moment.
     * @return PlaySession The same save, back on `narrator_turn` at the same beat.
     *
     * @throws IllegalLoopTransitionException When the save is not on `player_moment`.
     */
    public function resumeFromPlayerMoment(PlaySession $session): PlaySession
    {
        $this->guard($session, StateNode::PlayerMoment, 'resumeFromPlayerMoment');

        return $this->moveTo($session, StateNode::NarratorTurn);
    }

    /**
     * Close the beat and resume at the next one:
     * `beat_complete -> narrator_turn` (repositioned to the next beat).
     *
     * This is the `narrator_resumes` edge for a completed beat. The save is
     * repositioned to the next beat in document order ({@see BeatSequence}) and
     * handed back to the narrator. When no next beat exists the save stays on
     * `beat_complete` as a terminal end-of-story node — the loop-exit subsystem
     * (scene/chapter boundary batching) arrives in Phase 4 (PH-38).
     *
     * @param  PlaySession  $session  The save whose narrator turn closed the beat.
     * @return PlaySession The same save, on `narrator_turn` at the next beat, or terminal on `beat_complete`.
     *
     * @throws IllegalLoopTransitionException When the save is not on `beat_complete`.
     */
    public function completeBeat(PlaySession $session): PlaySession
    {
        $this->guard($session, StateNode::BeatComplete, 'completeBeat');

        $next = $session->current_beat_id === null
            ? null
            : $this->beats->next($session->currentBeat()->firstOrFail());

        if ($next === null) {
            // No next beat: the story is finished. Hold at beat_complete until
            // the Phase 4 boundary/loop-exit subsystem decides what follows.
            return $session;
        }

        return $this->moveToBeat($session, $next);
    }

    /**
     * Assert the save is on the node a transition requires, failing closed.
     *
     * @param  PlaySession  $session  The save being transitioned.
     * @param  StateNode  $expected  The node the transition is valid from.
     * @param  string  $transition  The transition method name (for the message).
     *
     * @throws IllegalLoopTransitionException When the save is on a different node.
     */
    private function guard(PlaySession $session, StateNode $expected, string $transition): void
    {
        if ($session->state_node !== $expected) {
            throw IllegalLoopTransitionException::from($session->state_node, $transition);
        }
    }

    /**
     * Persist a node-only transition and return the refreshed save.
     *
     * @param  PlaySession  $session  The save to move.
     * @param  StateNode  $node  The node to move it to.
     */
    private function moveTo(PlaySession $session, StateNode $node): PlaySession
    {
        $session->update(['state_node' => $node]);

        return $session;
    }

    /**
     * Reposition the save at a beat and resume the narrator there.
     *
     * Stamps the full chapter/scene/beat position from the target beat (the
     * same shape the fork writes) and returns to `narrator_turn`.
     *
     * @param  PlaySession  $session  The save to reposition.
     * @param  Beat  $beat  The beat to move to (its scene is eager-loaded by {@see BeatSequence}).
     */
    private function moveToBeat(PlaySession $session, Beat $beat): PlaySession
    {
        $session->update([
            'state_node' => StateNode::NarratorTurn,
            'current_chapter_id' => $beat->scene->chapter_id,
            'current_scene_id' => $beat->scene_id,
            'current_beat_id' => $beat->id,
        ]);

        return $session;
    }
}
