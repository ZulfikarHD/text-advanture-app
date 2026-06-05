<?php

namespace App\Services\Llm\Data;

use App\Contracts\Llm\LlmClient;
use App\Enums\LlmRole;
use App\Models\PlaySession;
use App\Models\ReviewItem;
use App\Models\Story;
use App\Models\User;
use App\Services\Llm\ModelRoleResolver;

/**
 * An immutable description of one LLM call routed through {@see LlmClient}.
 *
 * Carries the resolved transport coordinates (model slug + params), the message
 * payload, and the owner whose encrypted key authenticates the call. Optional
 * `story` / `session` / `reviewItem` links are recorded on the `llm_calls` log
 * so a call can be traced back to the artifact it produced (ADR 0017 §4).
 *
 * The caller resolves `modelSlug` + `params` from a {@see LlmRole} via
 * {@see ModelRoleResolver} before constructing the request -
 * the client itself never picks a model.
 */
final readonly class LlmRequest
{
    /**
     * @param  array<int, array{role: string, content: string}>  $messages  Chat messages sent verbatim to the provider.
     * @param  array<string, mixed>  $params  Model parameters (temperature, max_tokens, ...).
     */
    public function __construct(
        public LlmRole $role,
        public string $modelSlug,
        public array $messages,
        public User $owner,
        public array $params = [],
        public ?Story $story = null,
        public ?PlaySession $session = null,
        public ?ReviewItem $reviewItem = null,
    ) {}
}
