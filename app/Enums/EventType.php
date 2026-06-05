<?php

namespace App\Enums;

/**
 * Kind of entry in the immediate-context timeline (ADR 0016).
 *
 * Classifies each event in the bounded ~2000-token window: narration, a
 * player input, an NPC action, or a system note. Mirrors the `events.type`
 * DB enum.
 */
enum EventType: string
{
    case Narration = 'narration';
    case PlayerInput = 'player_input';
    case NpcAction = 'npc_action';
    case System = 'system';
}
