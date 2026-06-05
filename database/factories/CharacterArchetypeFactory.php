<?php

namespace Database\Factories;

use App\Models\CharacterArchetype;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CharacterArchetype>
 */
class CharacterArchetypeFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'slug' => fake()->unique()->slug(2),
            'name' => fake()->firstName(),
            'description' => fake()->sentence(),
            'base_opacity' => fake()->numberBetween(0, 100),
            'suggested_live_axes' => ['affection', 'trust', 'fear', 'romantic'],
            'default_disposition_priors' => [],
            'default_registers' => [],
            'default_sensitivities' => [],
            'voice_scaffold' => null,
        ];
    }
}
