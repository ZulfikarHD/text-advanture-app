<?php

namespace App\Services\Llm;

use App\Enums\Provider;
use App\Models\User;
use App\Services\Llm\Data\ProviderModel;
use App\Services\ProviderCredentialService;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * Lists the models an owner's key can reach, for the role -> model picker.
 *
 * Reuses the owner's encrypted key to read the provider's `/models` catalog
 * (the same probe the connection test uses) and normalises it for the UI, so
 * authors choose from real, reachable models instead of typing a slug by hand.
 * The catalog is shared across all role pickers on the page, so it is cached
 * briefly per owner; transient failures fall through to an empty list rather
 * than being cached, leaving the picker to fall back to manual entry.
 */
class ProviderModelCatalog
{
    /**
     * How long a fetched catalog stays cached before it is refreshed.
     */
    private const CACHE_TTL_MINUTES = 30;

    public function __construct(private readonly ProviderCredentialService $credentials) {}

    /**
     * List the reachable models for the owner, or an empty list when no key is
     * stored or the provider could not be reached.
     *
     * @return list<array{id: string, name: string, contextLength: int|null}>
     */
    public function for(User $user, Provider $provider = Provider::OpenRouter): array
    {
        $credential = $this->credentials->for($user, $provider);

        if ($credential === null) {
            return [];
        }

        $cacheKey = $this->cacheKey($user, $provider);
        $cached = Cache::get($cacheKey);

        if (is_array($cached)) {
            return $cached;
        }

        $baseUrl = rtrim($credential->base_url ?: (string) config('services.openrouter.base_url'), '/');
        $models = $this->fetch($credential->api_key, $baseUrl);

        // Only cache a real catalog - never poison the cache with an empty list
        // from a transient failure or an unconfigured key.
        if ($models !== []) {
            Cache::put($cacheKey, $models, now()->addMinutes(self::CACHE_TTL_MINUTES));
        }

        return $models;
    }

    /**
     * Drop the cached catalog for an owner so the next read refetches it.
     *
     * Called when the owner's key changes or is removed - the cached list was
     * fetched with the old key and could otherwise be served stale for the TTL.
     */
    public function forget(User $user, Provider $provider = Provider::OpenRouter): void
    {
        Cache::forget($this->cacheKey($user, $provider));
    }

    /**
     * The per-owner cache key for a provider's catalog.
     */
    private function cacheKey(User $user, Provider $provider): string
    {
        return sprintf('provider-models:%d:%s', $user->getKey(), $provider->value);
    }

    /**
     * Fetch + normalise the provider catalog, sorted by display name.
     *
     * @return list<array{id: string, name: string, contextLength: int|null}>
     */
    private function fetch(string $apiKey, string $baseUrl): array
    {
        try {
            $response = Http::withToken($apiKey)
                ->timeout((int) config('services.openrouter.timeout', 60))
                ->connectTimeout((int) config('services.openrouter.connect_timeout', 10))
                ->acceptJson()
                ->get($baseUrl.'/models');
        } catch (ConnectionException) {
            return [];
        }

        if ($response->failed()) {
            return [];
        }

        return collect((array) data_get($response->json(), 'data', []))
            ->map(fn (mixed $model): ?ProviderModel => is_array($model) ? ProviderModel::fromArray($model) : null)
            ->filter()
            ->sortBy(fn (ProviderModel $model): string => mb_strtolower($model->name))
            ->map(fn (ProviderModel $model): array => $model->toArray())
            ->values()
            ->all();
    }
}
