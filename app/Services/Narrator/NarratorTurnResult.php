<?php

namespace App\Services\Narrator;

use App\Contracts\Llm\LlmClient;
use App\Enums\ElapsedBucket;
use App\Enums\Handoff;

/**
 * The structured output of one narrator prose call (S-4.2.1, ADR 0016 §4).
 *
 * The prose call returns all three fields in a single call, so the `handoff`
 * is the turn's own output - not a separate classifier pass - and drives the
 * state machine's next node. Only ever built from a payload already validated
 * by {@see LlmClient::completeStructured()} against
 * {@see NarratorProseSchema}, so {@see self::fromParsed()} can map the enums
 * safely. `elapsedBucket` is captured now but only consumed by decay in Phase 5.
 */
final readonly class NarratorTurnResult
{
    /**
     * @param  string  $prose  The narrated text for the turn.
     * @param  Handoff  $handoff  Who acts next (player_moment | beat_complete this phase).
     * @param  ElapsedBucket  $elapsedBucket  The narrator's inferred in-world time gap.
     */
    public function __construct(
        public string $prose,
        public Handoff $handoff,
        public ElapsedBucket $elapsedBucket,
    ) {}

    /**
     * Map a schema-validated prose payload into a typed result.
     *
     * @param  array<string, mixed>  $parsed  The validated `completeStructured` payload.
     */
    public static function fromParsed(array $parsed): self
    {
        return new self(
            prose: (string) $parsed['prose'],
            handoff: Handoff::from((string) $parsed['handoff']),
            elapsedBucket: ElapsedBucket::from((string) $parsed['elapsed_bucket']),
        );
    }
}
