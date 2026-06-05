<?php

namespace Database\Factories;

use App\Models\Chapter;
use App\Models\RevealLedger;
use App\Models\Story;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RevealLedger>
 */
class RevealLedgerFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'story_id' => Story::factory(),
            'character_id' => null,
            'fact' => fake()->unique()->slug(),
            'reveal_chapter_id' => Chapter::factory(),
            'who_knows' => [],
            'notes' => null,
        ];
    }
}
