<?php

namespace Database\Factories;

use App\Enums\LlmCallStatus;
use App\Enums\LlmRole;
use App\Models\LlmCall;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LlmCall>
 */
class LlmCallFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            // Null by default so append-only/schema tests can insert without an
            // authenticated owner; real calls always stamp the owner.
            'user_id' => null,
            'session_id' => null,
            'story_id' => null,
            'role' => LlmRole::NarratorProse,
            'model_slug' => 'anthropic/claude-sonnet-4',
            'status' => LlmCallStatus::Ok,
            'prompt_tokens' => fake()->numberBetween(50, 4000),
            'completion_tokens' => fake()->numberBetween(50, 2000),
            'cost_micros_usd' => fake()->numberBetween(100, 50000),
            'latency_ms' => fake()->numberBetween(200, 5000),
            'error' => null,
            'review_item_id' => null,
            'messages' => null,
        ];
    }

    /**
     * Attribute the call to a specific owner.
     */
    public function forOwner(User $user): static
    {
        return $this->state(fn (): array => ['user_id' => $user->getKey()]);
    }
}
