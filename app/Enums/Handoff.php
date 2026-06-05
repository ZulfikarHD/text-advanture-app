<?php

namespace App\Enums;

/**
 * Narrator handoff signal at the end of an event (ADR 0016).
 *
 * Tells the loop who acts next: hand to the player, hand to an NPC, or close
 * the beat. Mirrors the nullable `events.handoff` DB enum.
 */
enum Handoff: string
{
    case PlayerMoment = 'player_moment';
    case NpcMoment = 'npc_moment';
    case BeatComplete = 'beat_complete';
}
