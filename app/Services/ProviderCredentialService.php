<?php

namespace App\Services;

use App\Enums\Provider;
use App\Models\ProviderCredential;
use App\Models\User;

/**
 * Manages a user's encrypted LLM-provider API key (S-5.1.1 / S-5.1.3).
 *
 * A thin service over {@see ProviderCredential}: stores/replaces the key
 * (recording only `last_four` for masked display), reads the current
 * credential, and removes it. Queries are constrained to the given user both
 * explicitly here and by the owner global scope, so a key never crosses owners.
 */
class ProviderCredentialService
{
    /**
     * Store or replace a user's API key for a provider.
     *
     * @param  string  $apiKey  The raw key; stored encrypted, never returned in plaintext.
     */
    public function store(
        User $user,
        string $apiKey,
        Provider $provider = Provider::OpenRouter,
        ?string $baseUrl = null,
    ): ProviderCredential {
        return ProviderCredential::updateOrCreate(
            ['user_id' => $user->getKey(), 'provider' => $provider],
            [
                'api_key' => $apiKey,
                'last_four' => substr($apiKey, -4),
                'base_url' => $baseUrl,
            ],
        );
    }

    /**
     * Get a user's current credential for a provider, or null if none is stored.
     */
    public function for(User $user, Provider $provider = Provider::OpenRouter): ?ProviderCredential
    {
        return ProviderCredential::query()
            ->where('user_id', $user->getKey())
            ->where('provider', $provider)
            ->first();
    }

    /**
     * Remove a user's stored credential for a provider, if any.
     */
    public function forget(User $user, Provider $provider = Provider::OpenRouter): void
    {
        ProviderCredential::query()
            ->where('user_id', $user->getKey())
            ->where('provider', $provider)
            ->delete();
    }
}
