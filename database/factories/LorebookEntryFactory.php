<?php

namespace Database\Factories;

use App\Models\LorebookEntry;
use App\Models\Story;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LorebookEntry>
 */
class LorebookEntryFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'story_id' => Story::factory(),
            'title' => fake()->optional()->sentence(3),
            'keywords' => [fake()->word(), fake()->word()],
            'content' => fake()->paragraph(),
            'min_reveal_chapter_id' => null,
        ];
    }
}
