<?php

namespace Database\Factories;

use App\Enums\Fidelity;
use App\Models\BeatRecord;
use App\Models\BeatWitness;
use App\Models\Character;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BeatWitness>
 */
class BeatWitnessFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'beat_record_id' => BeatRecord::factory(),
            'character_id' => Character::factory(),
            'fidelity' => Fidelity::Full,
        ];
    }
}
