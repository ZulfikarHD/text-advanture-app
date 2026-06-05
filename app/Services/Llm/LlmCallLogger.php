<?php

namespace App\Services\Llm;

use App\Enums\LlmCallStatus;
use App\Models\LlmCall;
use App\Services\Llm\Data\LlmRequest;
use App\Services\Llm\Data\TokenUsage;

/**
 * Writes the append-only `llm_calls` log for every model call (ADR 0017 §4-§5).
 *
 * Records role, resolved model, usage, provider cost (USD micro-units), latency
 * and status - success or failure - linked to the owner and any session / story
 * / review artifact. Full message bodies are save-realm-sensitive (they may
 * embed a character's private state), so they are persisted only when the debug
 * gate `services.openrouter.log_messages` is on; otherwise only counts are kept.
 */
class LlmCallLogger
{
    /**
     * Record one call outcome as an append-only log row.
     *
     * @param  LlmRequest  $request  The originating request (owner + links + messages).
     * @param  LlmCallStatus  $status  The terminal outcome (`Ok` or `Failed`).
     * @param  TokenUsage  $usage  Provider-reported token counts (may be empty on failure).
     * @param  int|null  $costMicrosUsd  Provider cost in USD micro-units, or null when unknown.
     * @param  int|null  $latencyMs  Wall-clock latency in milliseconds.
     * @param  string|null  $error  A short failure reason, or null on success.
     * @return LlmCall The persisted log row.
     */
    public function record(
        LlmRequest $request,
        LlmCallStatus $status,
        TokenUsage $usage,
        ?int $costMicrosUsd,
        ?int $latencyMs,
        ?string $error = null,
    ): LlmCall {
        return LlmCall::create([
            // Stamp ownership explicitly so the log is owner-scoped even when the
            // call runs off-session (queue / console), where Auth is absent.
            'user_id' => $request->owner->getKey(),
            'session_id' => $request->session?->getKey(),
            'story_id' => $request->story?->getKey(),
            'role' => $request->role,
            'model_slug' => $request->modelSlug,
            'status' => $status,
            'prompt_tokens' => $usage->promptTokens,
            'completion_tokens' => $usage->completionTokens,
            'cost_micros_usd' => $costMicrosUsd,
            'latency_ms' => $latencyMs,
            'error' => $error,
            'review_item_id' => $request->reviewItem?->getKey(),
            'messages' => $this->shouldLogBodies() ? $request->messages : null,
        ]);
    }

    /**
     * Whether full message bodies may be persisted (debug gate, off by default).
     */
    private function shouldLogBodies(): bool
    {
        return (bool) config('services.openrouter.log_messages', false);
    }
}
