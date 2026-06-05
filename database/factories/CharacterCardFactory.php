<?php

namespace Database\Factories;

use App\Models\Chapter;
use App\Models\Character;
use App\Models\CharacterCard;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CharacterCard>
 */
class CharacterCardFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'character_id' => Character::factory(),
            'chapter_id' => Chapter::factory(),
            'folded_identity' => fake()->paragraph(),
            'knowledge_boundary' => ['knows' => [], 'does_not_know' => []],
            'disposition_priors' => [],
            'voice' => [],
            'tells' => [],
            'appearance' => null,
            'compiled_source_hash' => null,
            'review_item_id' => null,
        ];
    }
}
