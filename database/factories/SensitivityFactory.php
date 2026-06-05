<?php

namespace Database\Factories;

use App\Enums\SensitivityChannel;
use App\Enums\SensitivityTarget;
use App\Enums\SensitivityWeight;
use App\Models\Character;
use App\Models\Sensitivity;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Sensitivity>
 */
class SensitivityFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'character_id' => Character::factory(),
            'slug' => fake()->unique()->slug(),
            'detect' => fake()->sentence(),
            'target' => SensitivityTarget::Actor,
            'axes' => ['affection' => 'down', 'trust' => 'down'],
            'weight' => SensitivityWeight::Medium,
            'channel' => SensitivityChannel::DriftOnly,
        ];
    }
}
