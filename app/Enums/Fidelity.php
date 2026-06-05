<?php

namespace App\Enums;

/**
 * How completely a character witnessed a beat (ADR 0007).
 *
 * Filters and projects the beat excerpt per NPC: `Full` saw everything,
 * `Overheard` caught it indirectly, `Partial` only fragments. Mirrors the
 * `beat_witnesses.fidelity` DB enum.
 */
enum Fidelity: string
{
    case Full = 'full';
    case Overheard = 'overheard';
    case Partial = 'partial';
}
