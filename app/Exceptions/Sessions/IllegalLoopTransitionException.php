<?php

namespace App\Exceptions\Sessions;

use App\Enums\Handoff;
use App\Enums\StateNode;
use App\Services\SessionStateMachine;
use RuntimeException;

/**
 * Thrown when a narrator-loop transition is requested from a state node that
 * does not allow it (S-3.1.1 / S-3.1.2, ADR 0016).
 *
 * The session state machine is the loop's only conductor and its spine is
 * deterministic, so an out-of-order transition (e.g. applying a handoff while
 * not on `narrator_turn`, or resuming while not on `player_moment`) is a
 * programming error, never a player-reachable state. {@see SessionStateMachine}
 * fails closed with this error rather than silently advancing into an
 * inconsistent position.
 *
 * The `npc_moment` handoff is a special case: it is a valid {@see Handoff}
 * value in the registry but its branch is not wired until Phase 2, so it is
 * rejected here with a distinct, explicit message.
 */
class IllegalLoopTransitionException extends RuntimeException
{
    /**
     * Build the exception for a transition attempted from the wrong node.
     *
     * @param  StateNode  $current  The node the save is actually on.
     * @param  string  $transition  The transition method that was rejected.
     */
    public static function from(StateNode $current, string $transition): self
    {
        return new self("Cannot run loop transition [{$transition}] from state node [{$current->value}].");
    }

    /**
     * Build the exception for the not-yet-reachable `npc_moment` handoff.
     *
     * The `npc_moment` branch lights up in Phase 2; this phase routes only
     * `player_moment` and `beat_complete`.
     */
    public static function npcMomentNotReachable(): self
    {
        return new self('Handoff ['.Handoff::NpcMoment->value.'] is not reachable until Phase 2; this phase routes only player_moment and beat_complete.');
    }
}
