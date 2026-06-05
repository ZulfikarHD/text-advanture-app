<?php

namespace Database\Factories;

use App\Models\ChapterLog;
use App\Models\PlaySession;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ChapterLog>
 */
class ChapterLogFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'session_id' => PlaySession::factory(),
            'chapter_id' => null,
            'summary' => null,
            'events' => [],
        ];
    }
}
