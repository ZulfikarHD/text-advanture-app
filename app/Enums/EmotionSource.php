<?php

namespace App\Enums;

/**
 * What installed an active emotion (ADR 0014 §5).
 *
 * Whether the feeling came from an appraisal, a rupture event, or was authored
 * directly. Mirrors the `active_emotions.source` DB enum.
 */
enum EmotionSource: string
{
    case Appraisal = 'appraisal';
    case Rupture = 'rupture';
    case Authored = 'authored';
}
