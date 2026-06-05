<?php

namespace App\Enums;

/**
 * How a sensitivity expresses itself on the relationship axes (ADR 0005).
 *
 * Whether it only drifts, only ruptures, or scales with severity. Mirrors the
 * `sensitivities.channel` and `universal_priors.channel` DB enums.
 */
enum SensitivityChannel: string
{
    case DriftOnly = 'drift_only';
    case RuptureOnly = 'rupture_only';
    case ScalesWithSeverity = 'scales_with_severity';
}
