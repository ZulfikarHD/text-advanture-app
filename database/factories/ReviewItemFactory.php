<?php

namespace Database\Factories;

use App\Enums\ProducerType;
use App\Enums\ReviewStatus;
use App\Models\ReviewItem;
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
}
