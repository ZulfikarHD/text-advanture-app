<?php

namespace App\Services\Llm;

use App\Enums\Provider;
use App\Models\User;
use App\Services\Llm\Data\ConnectionTestResult;
use App\Services\ProviderCredentialService;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

/**
 * Validates a stored provider key against the live provider (S-5.1.2).
 *
 * Hits OpenRouter's `/models` endpoint with the owner's key so the user learns
 * the key works - and which models are reachable - before relying on it during
 * play. This is a key-validation probe, not a role call: it never writes to the
 * `llm_calls` log and never returns the key.
 */
class ConnectionTester
{
    public function __construct(private readonly ProviderCredentialService $credentials) {}

    /**
     * Test the owner's stored key against the provider.
     *
     * @param  User  $user  The owner whose stored key is tested.
     * @param  Provider  $provider  The provider gateway to probe.
     * @return ConnectionTestResult Pass with reachable models, or fail with the provider's reason.
     */
    public function test(User $user, Provider $provider = Provider::OpenRouter): ConnectionTestResult
    {
        $credential = $this->credentials->for($user, $provider);

        if ($credential === null) {
            return ConnectionTestResult::fail(__('No API key is stored for this account yet.'));
        }

        $baseUrl = rtrim($credential->base_url ?: (string) config('services.openrouter.base_url'), '/');

        try {
            $response = Http::withToken($credential->api_key)
                ->timeout((int) config('services.openrouter.timeout', 60))
                ->connectTimeout((int) config('services.openrouter.connect_timeout', 10))
                ->acceptJson()
                ->get($baseUrl.'/models');
        } catch (ConnectionException $e) {
            return ConnectionTestResult::fail(__('Could not reach the provider: :reason', ['reason' => $e->getMessage()]));
        }

        if ($response->failed()) {
            $reason = data_get($response->json(), 'error.message');

            return ConnectionTestResult::fail(
                is_string($reason) && $reason !== '' ? $reason : __('Provider returned HTTP :status.', ['status' => $response->status()]),
            );
        }

        $models = collect((array) data_get($response->json(), 'data', []))
            ->pluck('id')
            ->filter(fn (mixed $id): bool => is_string($id) && $id !== '')
            ->values();

        return ConnectionTestResult::pass($models->count(), $models->take(5)->all());
    }
}
