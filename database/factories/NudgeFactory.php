<?php

namespace Database\Factories;

use App\Enums\NudgeLevel;
use App\Enums\NudgeSource;
use App\Models\Character;
use App\Models\Nudge;
use App\Models\PlaySession;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Nudge>
 */
class NudgeFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'session_id' => PlaySession::factory(),
            'beat_id' => null,
            'character_id' => Character::factory(),
            'kind' => ['goal'],
            'level' => NudgeLevel::L0,
            'text' => fake()->sentence(),
            'target' => null,
            'goal' => null,
            'source' => NudgeSource::Derived,
            'is_break_glass' => false,
            'review_item_id' => null,
        ];
    }
}
