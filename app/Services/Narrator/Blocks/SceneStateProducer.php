<?php

namespace App\Services\Narrator\Blocks;

use App\Models\Character;
use App\Services\Narrator\NarratorContext;

/**
 * Folds the `[SCENE STATE]` block — the running scene context-memory (ADR 0016).
 *
 * This phase renders the present cast (name + appearance from each character's
 * current-chapter card) and appends the scene summary when one exists. The
 * bounded immediate-context window and the canonical scene log feed in at E5.2;
 * until then this block carries only the authored scene surface. Leak rule: none.
 */
final class SceneStateProducer implements BlockProducer
{
    public function blockKey(): string
    {
        return 'SCENE_STATE';
    }

    public function produce(NarratorContext $context): ?string
    {
        $lines = [];

        $present = $context->presentCharacters();
        if ($present->isNotEmpty()) {
            $cast = $present
                ->map(fn (Character $character): string => $this->describe($context, $character))
                ->implode('; ');

            $lines[] = 'Present: '.$cast.'.';
        }

        $summary = $context->sceneSummaryText;
        if ($summary !== null && trim($summary) !== '') {
            $lines[] = 'Scene so far: '.$summary;
        }

        return $lines === [] ? null : implode("\n", $lines);
    }

    /**
     * Render one present character as `Name (appearance)`, or just the name.
     */
    private function describe(NarratorContext $context, Character $character): string
    {
        $appearance = $context->appearanceFor($character);

        if ($appearance !== null && trim($appearance) !== '') {
            return "{$character->name} ({$appearance})";
        }

        return $character->name;
    }
}
