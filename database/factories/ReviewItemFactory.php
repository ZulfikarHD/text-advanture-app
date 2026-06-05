<?php

namespace Database\Factories;

use App\Enums\ProducerType;
use App\Enums\ReviewStatus;
use App\Models\ReviewItem;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ReviewItem>
 */
class ReviewItemFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            // Null by default so schema tests can insert without an authenticated
            // owner; real proposals stamp the owner via the owner scope.
            'user_id' => null,
            'session_id' => null,
            'producer_type' => ProducerType::CardCompile,
            'producer_id' => null,
            'payload' => ['note' => fake()->sentence()],
            'status' => ReviewStatus::Pending,
            'edited_payload' => null,
            'reviewed_at' => null,
            'reviewed_by' => null,
        ];
    }

    /**
     * Attribute the proposal to a specific owner.
     */
    public function forOwner(User $user): static
    {
        return $this->state(fn (): array => ['user_id' => $user->getKey()]);
    }
}
