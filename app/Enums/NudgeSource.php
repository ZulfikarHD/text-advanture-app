<?php

namespace App\Enums;

/**
 * What produced a nudge (ADR 0008/0015 §2).
 *
 * `Derived` was compiled from beat intent; `Authored` was written directly by
 * the author. Mirrors the `nudges.source` DB enum.
 */
enum NudgeSource: string
{
    case Derived = 'derived';
    case Authored = 'authored';
}
