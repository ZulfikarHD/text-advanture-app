<?php

namespace Database\Factories;

use App\Models\BeatRecord;
use App\Models\BeatTrueState;
use App\Models\Character;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BeatTrueState>
 */
class BeatTrueStateFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'beat_record_id' => BeatRecord::factory(),
            'character_id' => Character::factory(),
            'private_text' => fake()->sentence(),
        ];
    }
}
