<?php

namespace App\Services\Narrator\Blocks;

use App\Enums\PovMode;
use App\Services\Narrator\NarratorContext;

/**
 * Folds the `[POV CONTRACT]` block — the viewpoint the narration must honor.
 *
 * Sources the current scene's `pov_mode`, `pov_anchor`, and `tone` (ADR 0009).
 * The anchor is a character slug (PH-35), resolved to a display name; the mode
 * is rendered via its {@see PovMode} label, falling back to the raw value for
 * an unknown mode. Leak rule: none.
 */
final class PovContractProducer implements BlockProducer
{
    public function blockKey(): string
    {
        return 'POV_CONTRACT';
    }

    public function produce(NarratorContext $context): ?string
    {
        $scene = $context->scene;

        if ($scene === null) {
            return null;
        }

        $mode = PovMode::tryFrom($scene->pov_mode)?->label() ?? $scene->pov_mode;
        $lines = ["Point of view: {$mode}."];

        $anchor = $context->characterName($scene->pov_anchor) ?? $scene->pov_anchor;
        if ($anchor !== null && trim($anchor) !== '') {
            $lines[] = "Anchored on: {$anchor}.";
        }

        if ($scene->tone !== null && trim($scene->tone) !== '') {
            $lines[] = "Tone: {$scene->tone}.";
        }

        return implode("\n", $lines);
    }
}
