<?php

namespace Database\Factories;

use App\Enums\SensitivityChannel;
use App\Enums\SensitivityWeight;
use App\Models\UniversalPrior;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<UniversalPrior>
 */
class UniversalPriorFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'slug' => fake()->unique()->slug(2),
            'detect' => fake()->sentence(),
            'axes' => ['affection' => 'down', 'trust' => 'down'],
            'default_weight' => SensitivityWeight::Medium,
            'channel' => SensitivityChannel::ScalesWithSeverity,
        ];
    }
}
