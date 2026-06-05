<?php

namespace App\Enums;

/**
 * Model tier for a character (ADR 0007).
 *
 * Drives how rich the compiled card is and which model role serves the
 * character: `Major` gets the full card / strong model, `Minor` a compressed
 * card / cheap model. Mirrors the `characters.model_tier` DB enum.
 */
enum ModelTier: string
{
    case Major = 'major';
    case Minor = 'minor';
}
