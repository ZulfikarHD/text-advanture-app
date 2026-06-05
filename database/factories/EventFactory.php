<?php

namespace Database\Factories;

use App\Enums\EventType;
use App\Models\Event;
use App\Models\PlaySession;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Event>
 */
class EventFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'session_id' => PlaySession::factory(),
            'beat_id' => null,
            'type' => EventType::Narration,
            'character_id' => null,
            'content' => fake()->paragraph(),
            'delivery' => null,
            'handoff' => null,
            'token_estimate' => null,
        ];
    }
}
