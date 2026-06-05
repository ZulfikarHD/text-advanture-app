<?php

namespace App\Services\Llm\Data;

/**
 * Token accounting reported by the provider for a single call (ADR 0017 §4).
 *
 * Mirrors the `prompt_tokens` / `completion_tokens` persisted on `llm_calls`.
 * Both are nullable because a failed call may complete no usable usage figure.
 */
final readonly class TokenUsage
{
    public function __construct(
        public ?int $promptTokens = null,
        public ?int $completionTokens = null,
    ) {}

    /**
     * Build usage from an OpenRouter `usage` object, tolerating missing keys.
     *
     * @param  array<string, mixed>  $usage
     */
    public static function fromArray(array $usage): self
    {
        return new self(
            promptTokens: isset($usage['prompt_tokens']) ? (int) $usage['prompt_tokens'] : null,
            completionTokens: isset($usage['completion_tokens']) ? (int) $usage['completion_tokens'] : null,
        );
    }
}
