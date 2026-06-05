<?php

namespace App\Enums;

/**
 * Which agent's prompt a prompt block renders into (ADR 0016/0020).
 *
 * A block may belong to the narrator prompt, an NPC prompt, or `Both`. Mirrors
 * the `prompt_blocks.agent` DB enum.
 */
enum BlockAgent: string
{
    case Narrator = 'narrator';
    case Npc = 'npc';
    case Both = 'both';
}
