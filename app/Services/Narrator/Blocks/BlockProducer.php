<?php

namespace App\Services\Narrator\Blocks;

use App\Services\Narrator\NarratorContext;

/**
 * Produces the folded body of one prompt block from turn context (ADR 0020 §2).
 *
 * Each producer owns exactly one registry key. The assembler selects and orders
 * blocks from the `prompt_blocks` registry, then asks the matching producer to
 * fold its data; a block whose producer is absent (e.g. MESH_AWARENESS this
 * phase) is skipped with no filler. Returning null/empty omits the block (e.g.
 * RESUME_ANCHOR when not resuming, or LOREBOOK with no keyword match).
 *
 * This phase folds deterministically (template, not the compiler LLM role); the
 * LLM-fold path via `compile_instruction` is deferred (PH-25/PH-39).
 */
interface BlockProducer
{
    /**
     * The `prompt_blocks.key` this producer folds (e.g. `POV_CONTRACT`).
     */
    public function blockKey(): string;

    /**
     * Fold this block's body from the turn context, or null to omit it.
     *
     * @param  NarratorContext  $context  The resolved data for the current turn.
     * @return string|null The folded body, or null when the block carries nothing this turn.
     */
    public function produce(NarratorContext $context): ?string;
}
