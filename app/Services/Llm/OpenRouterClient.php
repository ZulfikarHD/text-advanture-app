<?php

namespace App\Services\Llm;

use App\Contracts\Llm\LlmClient;
use App\Enums\LlmCallStatus;
use App\Exceptions\Llm\LlmCallFailedException;
use App\Exceptions\Llm\LlmStructuredOutputException;
use App\Services\Llm\Data\LlmRequest;
use App\Services\Llm\Data\LlmResponse;
use App\Services\Llm\Data\TokenUsage;
use App\Services\ProviderCredentialService;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;

/**
 * Thin OpenRouter implementation of {@see LlmClient} (ADR 0017 §1).
 *
 * Talks to OpenRouter's OpenAI-compatible Chat Completions endpoint over the
 * `Http` facade for full visibility into the exact bytes sent - which matters
 * because the engine is about what reaches the context. The owner's encrypted
 * key (never the env) authenticates each call (PH-18). Transport concerns live
 * here: bounded retry/backoff on 429/5xx and on malformed structured output,
 * provider-cost capture, and append-only logging. Isolation is NOT this class's
 * job; it sends whatever messages it is handed.
 */
class OpenRouterClient implements LlmClient
{
    public function __construct(
        private readonly ProviderCredentialService $credentials,
        private readonly LlmCallLogger $logger,
    ) {}

    /**
     * {@inheritDoc}
     */
    public function complete(LlmRequest $request): LlmResponse
    {
        return $this->dispatch($request, schema: null, schemaName: null);
    }

    /**
     * {@inheritDoc}
     */
    public function completeStructured(LlmRequest $request, array $schema, string $schemaName = 'structured_output'): LlmResponse
    {
        return $this->dispatch($request, schema: $schema, schemaName: $schemaName);
    }

    /**
     * Run a call through the retry envelope, validating + logging the outcome.
     *
     * @param  array<string, mixed>|null  $schema  Inner JSON schema for a structured call, or null for text.
     *
     * @throws LlmStructuredOutputException When a structured call never conforms within the bound.
     * @throws LlmCallFailedException When the transport fails or no key is configured.
     */
    private function dispatch(LlmRequest $request, ?array $schema, ?string $schemaName): LlmResponse
    {
        $credential = $this->credentials->for($request->owner);

        // Step 1: fail closed when the owner has no key - never silently no-op.
        if ($credential === null) {
            return $this->fail($request, $schema, 'No provider API key is configured for this account.', latencyMs: 0, structural: false);
        }

        $baseUrl = rtrim($credential->base_url ?: (string) config('services.openrouter.base_url'), '/');
        $payload = $this->buildPayload($request, $schema, $schemaName);

        $maxAttempts = ((int) config('services.openrouter.max_retries', 2)) + 1;
        $startedAt = microtime(true);
        $lastError = 'unknown error';
        $lastErrorIsStructural = false;

        // Step 2: attempt the call, retrying transient transport + malformed
        // structured output with backoff up to the configured bound.
        for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
            try {
                $response = Http::withToken($credential->api_key)
                    ->timeout((int) config('services.openrouter.timeout', 60))
                    ->connectTimeout((int) config('services.openrouter.connect_timeout', 10))
                    ->acceptJson()
                    ->asJson()
                    ->post($baseUrl.'/chat/completions', $payload);
            } catch (ConnectionException $e) {
                $lastError = 'connection error: '.$e->getMessage();
                $lastErrorIsStructural = false;
                $this->backoff($attempt, $maxAttempts);

                continue;
            }

            // Retryable: rate limits and provider-side errors.
            if ($response->status() === 429 || $response->serverError()) {
                $lastError = $this->extractProviderError($response);
                $lastErrorIsStructural = false;
                $this->backoff($attempt, $maxAttempts);

                continue;
            }

            // Non-retryable client errors (e.g. 401 bad key) stop immediately.
            if ($response->failed()) {
                $lastError = $this->extractProviderError($response);
                $lastErrorIsStructural = false;

                break;
            }

            $data = (array) $response->json();
            $content = data_get($data, 'choices.0.message.content');
            $usage = TokenUsage::fromArray((array) data_get($data, 'usage', []));
            $costMicros = $this->costToMicros(data_get($data, 'usage.cost'));

            // Step 3: structured calls must parse + validate, else they are retried.
            $parsed = null;
            if ($schema !== null) {
                $parsed = $this->decodeJson($content);

                if ($parsed === null || ! $this->conformsToSchema($parsed, $schema)) {
                    $lastError = 'structured output did not conform to the requested schema';
                    $lastErrorIsStructural = true;
                    $this->backoff($attempt, $maxAttempts);

                    continue;
                }
            }

            // Step 4: success - log an Ok row and return the validated result.
            $latencyMs = $this->elapsedMs($startedAt);
            $this->logger->record($request, LlmCallStatus::Ok, $usage, $costMicros, $latencyMs);

            return new LlmResponse(
                status: LlmCallStatus::Ok,
                modelSlug: $request->modelSlug,
                text: is_string($content) ? $content : null,
                parsed: $parsed,
                usage: $usage,
                costMicrosUsd: $costMicros ?? 0,
                latencyMs: $latencyMs,
                rawId: is_string(data_get($data, 'id')) ? data_get($data, 'id') : null,
            );
        }

        // Step 5: retries exhausted (or non-retryable) - record failure + surface.
        return $this->fail($request, $schema, $lastError, $this->elapsedMs($startedAt), $lastErrorIsStructural);
    }

    /**
     * Record a failed call and raise the appropriate exception (never returns).
     *
     * @param  array<string, mixed>|null  $schema
     *
     * @throws LlmStructuredOutputException
     * @throws LlmCallFailedException
     */
    private function fail(LlmRequest $request, ?array $schema, string $reason, int $latencyMs, bool $structural): never
    {
        $this->logger->record($request, LlmCallStatus::Failed, new TokenUsage, null, $latencyMs, $reason);

        if ($schema !== null && $structural) {
            throw new LlmStructuredOutputException($request->role, $request->modelSlug, $reason);
        }

        throw new LlmCallFailedException($request->role, $request->modelSlug, $reason);
    }

    /**
     * Build the Chat Completions request body from the resolved request.
     *
     * @param  array<string, mixed>|null  $schema
     * @return array<string, mixed>
     */
    private function buildPayload(LlmRequest $request, ?array $schema, ?string $schemaName): array
    {
        // Params (temperature, max_tokens, ...) form the base; model/messages and
        // cost accounting are always set so callers cannot accidentally drop them.
        $payload = $request->params;
        $payload['model'] = $request->modelSlug;
        $payload['messages'] = $request->messages;
        $payload['usage'] = ['include' => true];

        if ($schema !== null) {
            $payload['response_format'] = [
                'type' => 'json_schema',
                'json_schema' => [
                    'name' => $schemaName ?? 'structured_output',
                    'strict' => true,
                    'schema' => $schema,
                ],
            ];
        }

        return $payload;
    }

    /**
     * Sleep with exponential backoff between attempts (skipped after the last).
     */
    private function backoff(int $attempt, int $maxAttempts): void
    {
        if ($attempt >= $maxAttempts) {
            return;
        }

        $base = (int) config('services.openrouter.retry_base_delay_ms', 250);

        Sleep::for($base * (2 ** ($attempt - 1)))->milliseconds();
    }

    /**
     * Decode a JSON content string into an array, or null if it is not parseable.
     *
     * @return array<string, mixed>|null
     */
    private function decodeJson(mixed $content): ?array
    {
        if (! is_string($content)) {
            return null;
        }

        $decoded = json_decode($content, true);

        return is_array($decoded) ? $decoded : null;
    }

    /**
     * Validate a decoded payload against the required keys, types, and enums.
     *
     * @param  array<string, mixed>  $data
     * @param  array<string, mixed>  $schema
     */
    private function conformsToSchema(array $data, array $schema): bool
    {
        foreach ((array) ($schema['required'] ?? []) as $key) {
            if (! array_key_exists($key, $data)) {
                return false;
            }
        }

        foreach ((array) ($schema['properties'] ?? []) as $key => $definition) {
            if (! array_key_exists($key, $data)) {
                continue;
            }

            $expectedType = $definition['type'] ?? null;

            if ($expectedType !== null && ! $this->matchesType($data[$key], (string) $expectedType)) {
                return false;
            }

            // A declared enum constrains the value to a closed vocabulary, so a
            // value outside it (e.g. an out-of-phase handoff signal) is treated
            // as non-conforming - the call is retried then surfaced rather than
            // trusted (S-4.2.2). Schemas with no enum are unaffected.
            $allowed = $definition['enum'] ?? null;

            if (is_array($allowed) && ! in_array($data[$key], $allowed, true)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Check a value against a JSON-schema scalar/compound type name.
     */
    private function matchesType(mixed $value, string $type): bool
    {
        return match ($type) {
            'string' => is_string($value),
            'integer' => is_int($value),
            'number' => is_int($value) || is_float($value),
            'boolean' => is_bool($value),
            'array' => is_array($value) && array_is_list($value),
            'object' => is_array($value) && ! array_is_list($value),
            'null' => is_null($value),
            default => true,
        };
    }

    /**
     * Convert a provider-reported USD cost (dollars) into integer micro-units.
     */
    private function costToMicros(mixed $cost): ?int
    {
        if (! is_numeric($cost)) {
            return null;
        }

        return (int) round(((float) $cost) * 1_000_000);
    }

    /**
     * Extract a human-readable provider error, falling back to the status line.
     */
    private function extractProviderError(Response $response): string
    {
        $message = data_get($response->json(), 'error.message');

        return is_string($message) && $message !== ''
            ? $message
            : 'HTTP '.$response->status();
    }

    /**
     * Whole milliseconds elapsed since a `microtime(true)` start mark.
     */
    private function elapsedMs(float $startedAt): int
    {
        return (int) round((microtime(true) - $startedAt) * 1000);
    }
}
