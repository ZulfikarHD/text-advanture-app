<?php

namespace Database\Factories;

use App\Models\Character;
use App\Models\Register;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Register>
 */
class RegisterFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'character_id' => Character::factory(),
            'slug' => fake()->unique()->slug(),
            'archetype_id' => null,
            'dimensions' => [
                'disclosure' => 'medium',
                'proximity' => 'medium',
            ],
            'speech_ref' => null,
            'tells' => [],
            'is_pinned' => false,
        ];
    }
}
