<?php

namespace App\Enums;

/**
 * Node in the session state machine / narrator loop (ADR 0016).
 *
 * Tracks where a save currently sits in the narrator -> player -> narrator
 * spine. Mirrors the `sessions.state_node` DB enum.
 */
enum StateNode: string
{
    case SessionStart = 'session_start';
    case NarratorTurn = 'narrator_turn';
    case PlayerMoment = 'player_moment';
    case NpcMoment = 'npc_moment';
    case BeatComplete = 'beat_complete';

    /**
     * Human-readable label for the node (UI badges, save lists).
     */
    public function label(): string
    {
        return match ($this) {
            self::SessionStart => 'Session start',
            self::NarratorTurn => 'Narrator turn',
            self::PlayerMoment => 'Player moment',
            self::NpcMoment => 'NPC moment',
            self::BeatComplete => 'Beat complete',
        };
    }
}
