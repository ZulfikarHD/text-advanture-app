<?php

namespace App\Enums;

/**
 * Who a sensitivity's reaction is about (ADR 0005).
 *
 * Distinguishes whether the appraisal targets the actor, the beneficiary, or a
 * witnessed third party. Mirrors the `sensitivities.target` DB enum.
 */
enum SensitivityTarget: string
{
    case Actor = 'actor';
    case Beneficiary = 'beneficiary';
    case WitnessedThirdParty = 'witnessed_third_party';
}
