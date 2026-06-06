<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\LlmCall;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Shows the owner's LLM activity / cost log (S-5.3.1).
 *
 * Reads the append-only `llm_calls` log, owner-scoped by the model's global
 * scope so a user only ever sees their own calls. The list is a deferred prop -
 * the page shell renders immediately and the (potentially slow) log query
 * resolves in a follow-up request behind a skeleton. Cost is shown as the
 * provider-reported USD value; the message bodies are never exposed.
 */
class UsageController extends Controller
{
    /**
     * Render the usage log shell; the call list loads as a deferred prop.
     */
    public function index(Request $request): Response
    {
        return Inertia::render('engine/Usage', [
            'calls' => Inertia::defer(fn (): array => $this->ownerCalls()),
        ]);
    }

    /**
     * Build the owner-scoped, paginated, presentable call log.
     *
     * @return array<string, mixed> The paginator array (`data` + pagination meta).
     */
    private function ownerCalls(): array
    {
        return LlmCall::query()
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate(15)
            ->withQueryString()
            ->through(fn (LlmCall $call): array => [
                'id' => $call->id,
                'role' => $call->role->value,
                'roleLabel' => $call->role->label(),
                'modelSlug' => $call->model_slug,
                'status' => $call->status->value,
                'promptTokens' => $call->prompt_tokens,
                'completionTokens' => $call->completion_tokens,
                'costMicrosUsd' => $call->cost_micros_usd,
                'latencyMs' => $call->latency_ms,
                'createdAt' => $call->created_at?->toIso8601String(),
                'error' => $call->error,
            ])
            ->toArray();
    }
}
