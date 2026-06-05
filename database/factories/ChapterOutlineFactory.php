<?php

namespace Database\Factories;

use App\Enums\OutlineStatus;
use App\Models\ChapterOutline;
use App\Models\Story;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ChapterOutline>
 */
class ChapterOutlineFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'story_id' => Story::factory(),
            'chapter_id' => null,
            'raw_text' => fake()->paragraphs(3, true),
            'status' => OutlineStatus::Draft,
            'review_item_id' => null,
        ];
    }
}
