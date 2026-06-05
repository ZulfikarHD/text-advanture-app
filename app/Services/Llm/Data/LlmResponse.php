<?php

namespace App\Services\Llm\Data;

use App\Contracts\Llm\LlmClient;
use App\Enums\LlmCallStatus;
use App\Exceptions\Llm\LlmCallFailedException;
use App\Models\LlmCall;

/**
 * The result of a successful call through {@see LlmClient}.
 *
 * A failed or malformed call never produces a response - it raises an
 * {@see LlmCallFailedException} instead - so a returned
 * `LlmResponse` is always `Ok`. `parsed` is populated only for structured
 * calls (and only after schema validation passed). `costMicrosUsd` is the
 * provider-reported cost in USD micro-units, stored and displayed as USD.
 *
 * @see LlmCall
 */
final readonly class LlmResponse
{
    /**
     * @param  array<string, mixed>|null  $parsed  Schema-validated structured payload, or null for text calls.
     */
    public function __construct(
        public LlmCallStatus $status,
        public string $modelSlug,
        public ?string $text,
        public ?array $parsed,
        public TokenUsage $usage,
        public int $costMicrosUsd,
        public int $latencyMs,
        public ?string $rawId,
    ) {}
}
