<?php

namespace Database\Factories;

use App\Models\RegisterArchetype;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RegisterArchetype>
 */
class RegisterArchetypeFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'slug' => fake()->unique()->slug(2),
            'name' => fake()->words(2, true),
            'dimensions' => [
                'disclosure' => 'low',
                'proximity' => 'medium',
                'flow' => 'measured',
                'deflection' => 'high',
                'sincerity' => 'guarded',
                'composure' => 'high',
                'reads_target' => 'sharp',
            ],
            'description' => fake()->sentence(),
        ];
    }
}
