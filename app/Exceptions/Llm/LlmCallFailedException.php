<?php

namespace App\Exceptions\Llm;

use App\Enums\LlmRole;
use Throwable;

/**
 * Thrown when a model call exhausts its retries or fails unrecoverably.
 *
 * The matching `llm_calls` row is recorded as failed before this is raised, so
 * the caller is informed and the bad/absent output is never trusted
 * (ADR 0017 §5, S-5.2.3).
 */
class LlmCallFailedException extends LlmException
{
    public function __construct(
        public readonly LlmRole $role,
        public readonly string $modelSlug,
        string $reason,
        ?Throwable $previous = null,
    ) {
        parent::__construct(
            "LLM call for role [{$role->value}] on model [{$modelSlug}] failed: {$reason}",
            previous: $previous,
        );
    }
}
