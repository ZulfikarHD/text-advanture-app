<?php

namespace App\Enums;

/**
 * How an edge axis derives its awareness tier (ADR 0002/0007).
 *
 * `Auto` derives the tier from the magnitude of the value; `Capped` is a
 * deliberate blind spot that holds awareness below what the value implies.
 * Mirrors the `edge_axes.awareness_mode` DB enum.
 */
enum AwarenessMode: string
{
    case Auto = 'auto';
    case Capped = 'capped';
}
