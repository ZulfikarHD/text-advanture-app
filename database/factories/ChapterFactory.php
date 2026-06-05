<?php

namespace Database\Factories;

use App\Models\Chapter;
use App\Models\Story;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Chapter>
 */
class ChapterFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'story_id' => Story::factory(),
            'number' => fake()->numberBetween(1, 50),
            'title' => fake()->sentence(3),
            'pov_default' => fake()->firstName(),
            'outline' => null,
            'word_cap' => null,
        ];
    }
}
