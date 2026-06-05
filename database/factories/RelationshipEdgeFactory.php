<?php

namespace Database\Factories;

use App\Models\Character;
use App\Models\PlaySession;
use App\Models\RelationshipEdge;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RelationshipEdge>
 */
class RelationshipEdgeFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'session_id' => PlaySession::factory(),
            'from_character_id' => Character::factory(),
            'to_character_id' => Character::factory(),
            'register_base' => fake()->slug(2),
            'register_overrides' => null,
            'topic_flags' => null,
            'meta' => null,
        ];
    }
}
