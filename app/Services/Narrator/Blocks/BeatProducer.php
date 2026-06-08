<?php

namespace App\Services\Narrator\Blocks;

use App\Services\Narrator\NarratorContext;

/**
 * Folds the `[BEAT]` block — the authoring direction for the turn (ADR 0015).
 *
 * This phase carries only the beat's `goal` (its satisfaction anchor); the full
 * beat document (`intent`, `word_budget`, `nudge_target`) is authored in Phase 4
 * (PH-35) and folded then. Leak rule: omniscient_authoring (author-side input).
 */
final class BeatProducer implements BlockProducer
{
    public function blockKey(): string
    {
        return 'BEAT';
    }

    public function produce(NarratorContext $context): ?string
    {
        $beat = $context->beat;

        if ($beat === null || trim($beat->goal) === '') {
            return null;
        }

        return "Goal: {$beat->goal}";
    }
}
