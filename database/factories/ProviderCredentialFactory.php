<?php

namespace Database\Factories;

use App\Enums\Provider;
use App\Models\ProviderCredential;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProviderCredential>
 */
class ProviderCredentialFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $key = 'sk-or-v1-'.fake()->regexify('[a-f0-9]{32}');

        return [
            'user_id' => User::factory(),
            'provider' => Provider::OpenRouter,
            'api_key' => $key,
            'last_four' => substr($key, -4),
            'base_url' => null,
        ];
    }
}
