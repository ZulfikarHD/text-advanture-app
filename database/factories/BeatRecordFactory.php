<?php

namespace Database\Factories;

use App\Models\BeatRecord;
use App\Models\PlaySession;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BeatRecord>
 */
class BeatRecordFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'session_id' => PlaySession::factory(),
            'beat_id' => null,
            'surface' => fake()->paragraph(),
            'pov_anchor' => fake()->firstName(),
        ];
    }
}
