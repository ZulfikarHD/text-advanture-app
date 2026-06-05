<?php

namespace Database\Factories;

use App\Enums\SensitivityChannel;
use App\Enums\SensitivityTarget;
use App\Enums\SensitivityWeight;
use App\Models\AcquiredSensitivity;
use App\Models\Character;
use App\Models\PlaySession;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AcquiredSensitivity>
 */
class AcquiredSensitivityFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'session_id' => PlaySession::factory(),
            'character_id' => Character::factory(),
            'detect' => fake()->sentence(),
            'target' => SensitivityTarget::Actor,
            'axes' => ['trust' => 'down'],
            'weight' => SensitivityWeight::Medium,
            'channel' => SensitivityChannel::ScalesWithSeverity,
            'installed_by_delta_id' => null,
        ];
    }
}
