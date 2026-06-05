<?php

namespace App\Enums;

/**
 * Channel an axis delta arrived through (ADR 0003).
 *
 * `Drift` is gradual, soft-clamped change batched at scene boundaries;
 * `Rupture` is an immediate event that may reach the hard floor/cap. Mirrors
 * the `axis_deltas.channel` DB enum.
 */
enum DeltaChannel: string
{
    case Drift = 'drift';
    case Rupture = 'rupture';
}
