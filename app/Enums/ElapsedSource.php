<?php

namespace App\Enums;

/**
 * Provenance of a scene's elapsed-time bucket (ADR 0015 §6).
 *
 * Records whether the gap was authored, inferred by the narrator, or left at
 * the default. Mirrors the `scenes.elapsed_source` DB enum.
 */
enum ElapsedSource: string
{
    case Authored = 'authored';
    case NarratorInferred = 'narrator_inferred';
    case Default = 'default';
}
