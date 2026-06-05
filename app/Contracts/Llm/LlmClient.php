<?php

namespace App\Contracts\Llm;

use App\Exceptions\Llm\LlmCallFailedException;
use App\Exceptions\Llm\LlmStructuredOutputException;
use App\Services\Llm\Data\LlmRequest;
use App\Services\Llm\Data\LlmResponse;
use App\Services\Llm\OpenRouterClient;

/**
 * The single provider-agnostic chokepoint for every LLM call (ADR 0017 §1).
 *
 * All engine traffic flows through this interface; the active implementation is
 * {@see OpenRouterClient} (thin Http transport). Swapping the
 * provider means rebinding this interface - no caller changes. The client is
 * dumb transport: it sends whatever messages it is handed and never decides
 * context isolation, which stays upstream in the assembler and leak guards.
 */
interface LlmClient
{
    /**
     * Make a text/chat completion call.
     *
     * @param  LlmRequest  $request  The resolved model, messages, params, and owner.
     * @return LlmResponse Always `Ok`; the call is logged.
     *
     * @throws LlmCallFailedException When the call exhausts its retries or fails unrecoverably.
     */
    public function complete(LlmRequest $request): LlmResponse;

    /**
     * Make a structured completion call validated against a JSON schema.
     *
     * The provider is asked for a JSON-schema response; the parsed payload is
     * validated before return. A malformed/non-conforming payload is retried
     * with backoff up to the bound, then surfaced as a failure - never trusted.
     *
     * @param  LlmRequest  $request  The resolved model, messages, params, and owner.
     * @param  array<string, mixed>  $schema  A JSON schema object (`type`, `properties`, `required`).
     * @param  string  $schemaName  A short identifier for the schema (sent to the provider).
     * @return LlmResponse Always `Ok` with a populated `parsed` payload; the call is logged.
     *
     * @throws LlmStructuredOutputException When no schema-conforming payload is produced within the retry bound.
     * @throws LlmCallFailedException When the transport fails unrecoverably.
     */
    public function completeStructured(LlmRequest $request, array $schema, string $schemaName = 'structured_output'): LlmResponse;
}
