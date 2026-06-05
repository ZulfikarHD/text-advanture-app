<?php

namespace Database\Factories;

use App\Models\Character;
use App\Models\InternalState;
use App\Models\PlaySession;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<InternalState>
 */
class InternalStateFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'session_id' => PlaySession::factory(),
            'character_id' => Character::factory(),
            'mood' => null,
            'mood_override' => null,
            'motivation' => null,
            'masks' => null,
            'last_clocked_at' => null,
        ];
    }
}
