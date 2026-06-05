<?php

namespace App\Enums;

/**
 * Escalation rung of a psychological nudge (ADR 0008).
 *
 * The directed-pressure ladder from a gentle pull (`L0`) to a hard directive
 * (`L3`). Mirrors the `sessions.nudge_level` and `nudges.level` DB enums.
 */
enum NudgeLevel: string
{
    case L0 = 'L0';
    case L1 = 'L1';
    case L2 = 'L2';
    case L3 = 'L3';
}
