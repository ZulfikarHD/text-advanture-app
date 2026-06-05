<?php

namespace App\Services\Llm\Data;

/**
 * Outcome of a provider connection test (S-5.1.2).
 *
 * A pass reports the reachable model count and a small sample of slugs; a
 * failure carries the provider's reason so the user learns why before relying
 * on the key during play. This is a key-validation probe, not a role call - it
 * is never written to the `llm_calls` log.
 */
final readonly class ConnectionTestResult
{
    /**
     * @param  list<string>  $sampleModels  A short sample of reachable model slugs (success only).
     */
    private function __construct(
        public bool $ok,
        public ?int $reachableModelCount = null,
        public array $sampleModels = [],
        public ?string $failureReason = null,
    ) {}

    /**
     * A successful connection with the set of reachable models.
     *
     * @param  list<string>  $sampleModels
     */
    public static function pass(int $reachableModelCount, array $sampleModels): self
    {
        return new self(ok: true, reachableModelCount: $reachableModelCount, sampleModels: $sampleModels);
    }

    /**
     * A failed connection with the provider's (sanitised) reason.
     */
    public static function fail(string $reason): self
    {
        return new self(ok: false, failureReason: $reason);
    }

    /**
     * Shape the result for the client (no secrets are ever included).
     *
     * @return array{ok: bool, reachableModelCount: int|null, sampleModels: list<string>, failureReason: string|null}
     */
    public function toArray(): array
    {
        return [
            'ok' => $this->ok,
            'reachableModelCount' => $this->reachableModelCount,
            'sampleModels' => $this->sampleModels,
            'failureReason' => $this->failureReason,
        ];
    }
}
