<?php

namespace App\Enums;

/**
 * Direction of an axis delta (ADR 0003).
 *
 * Whether the appraised change pushes the axis value up or down. Mirrors the
 * `axis_deltas.direction` DB enum.
 */
enum DeltaDirection: string
{
    case Up = 'up';
    case Down = 'down';
}
