<?php

namespace Database\Factories;

use App\Models\PlaySession;
use App\Models\SceneSummary;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SceneSummary>
 */
class SceneSummaryFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'session_id' => PlaySession::factory(),
            'scene_id' => null,
            'summary' => fake()->paragraph(),
            'drift_applied' => false,
            'decay_applied' => false,
        ];
    }
}
