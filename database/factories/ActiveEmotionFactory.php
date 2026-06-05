<?php

namespace Database\Factories;

use App\Enums\EmotionSource;
use App\Models\ActiveEmotion;
use App\Models\InternalState;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ActiveEmotion>
 */
class ActiveEmotionFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'internal_state_id' => InternalState::factory(),
            'emotion' => fake()->randomElement(['calm', 'anxious', 'guilt', 'startled']),
            'intensity' => fake()->numberBetween(0, 100),
            'baseline' => 0,
            'reversion_rate' => 1.00,
            'drift_cap' => 3,
            'source' => EmotionSource::Appraisal,
            'installed_at' => now(),
            'last_clocked_at' => now(),
        ];
    }
}
