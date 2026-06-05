<?php

namespace App\Enums;

/**
 * Declared in-world time gap entering a scene (ADR 0015 §6).
 *
 * A coarse bucket rather than an exact duration; the narrator uses it to set
 * elapsed-time framing. Mirrors the `scenes.elapsed_bucket` DB enum.
 */
enum ElapsedBucket: string
{
    case Continuous = 'continuous';
    case Hours = 'hours';
    case Days = 'days';
    case Weeks = 'weeks';
    case Months = 'months';
    case Longer = 'longer';
}
