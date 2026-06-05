<?php

namespace Database\Factories;

use App\Enums\ModelTier;
use App\Models\Character;
use App\Models\Story;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Character>
 */
class CharacterFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'story_id' => Story::factory(),
            'slug' => fake()->unique()->slug(),
            'name' => fake()->name(),
            'bible_path' => null,
            'base_opacity' => fake()->numberBetween(0, 100),
            'live_axes' => ['affection', 'trust'],
            'model_tier' => ModelTier::Major,
            'is_player' => false,
        ];
    }

    /**
     * Indicate that the character is the player avatar (appearance-only).
     */
    public function player(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_player' => true,
            'model_tier' => ModelTier::Minor,
        ]);
    }
}
