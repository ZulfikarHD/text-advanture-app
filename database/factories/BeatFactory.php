<?php

namespace Database\Factories;

use App\Models\Beat;
use App\Models\Scene;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Beat>
 */
class BeatFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'scene_id' => Scene::factory(),
            'number' => fake()->numberBetween(1, 20),
            'intent' => fake()->sentence(),
            'goal' => fake()->sentence(3),
            'word_budget' => fake()->numberBetween(100, 600),
            'nudge_target_character_id' => null,
        ];
    }
}
