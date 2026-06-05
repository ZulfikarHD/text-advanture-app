<?php

namespace Database\Factories;

use App\Enums\StateNode;
use App\Models\PlaySession;
use App\Models\Story;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PlaySession>
 */
class PlaySessionFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'story_id' => Story::factory(),
            'name' => fake()->words(2, true),
            'state_node' => StateNode::SessionStart,
            'current_chapter_id' => null,
            'current_scene_id' => null,
            'current_beat_id' => null,
            'beat_word_count' => 0,
            'chapter_word_count' => 0,
            'nudge_level' => null,
            'resume_anchor' => null,
            'narrative_clock' => null,
            'last_played_at' => null,
        ];
    }
}
