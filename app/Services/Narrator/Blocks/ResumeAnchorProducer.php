<?php

namespace App\Services\Narrator\Blocks;

use App\Services\Narrator\NarratorContext;

/**
 * Folds the `[RESUME ANCHOR]` block — the continuity seam (ADR 0016 §5).
 *
 * The only narrator block in the user section. Injected only when resuming:
 * it renders whatever the session's `resume_anchor` holds (scene type, last
 * line, POV, tone) so the narrator continues rather than restarting the beat.
 * This phase builds the block plumbing; the anchor's content is produced on
 * pause in S-5.3.1, so the block is absent until then (leak rule: none).
 */
final class ResumeAnchorProducer implements BlockProducer
{
    public function blockKey(): string
    {
        return 'RESUME_ANCHOR';
    }

    public function produce(NarratorContext $context): ?string
    {
        $anchor = $context->session->resume_anchor;

        if ($anchor === null || $anchor === []) {
            return null;
        }

        $lines = [];

        foreach ($anchor as $key => $value) {
            $rendered = $this->render($value);

            if ($rendered === '') {
                continue;
            }

            $lines[] = ucfirst(str_replace('_', ' ', (string) $key)).': '.$rendered;
        }

        return $lines === [] ? null : implode("\n", $lines);
    }

    /**
     * Flatten a resume-anchor value to a line, JSON-encoding nested structures.
     *
     * @param  mixed  $value  A scalar or array fragment of the anchor.
     */
    private function render(mixed $value): string
    {
        if ($value === null || $value === '' || $value === []) {
            return '';
        }

        if (is_array($value)) {
            return (string) json_encode($value);
        }

        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        return trim((string) $value);
    }
}
