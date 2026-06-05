<?php

namespace App\Enums;

/**
 * Salience weight of a sensitivity or universal prior (ADR 0005).
 *
 * A coarse multiplier the rubric config maps to a numeric weight. Mirrors the
 * `sensitivities.weight` and `universal_priors.default_weight` DB enums.
 */
enum SensitivityWeight: string
{
    case Low = 'low';
    case Medium = 'medium';
    case High = 'high';
}
