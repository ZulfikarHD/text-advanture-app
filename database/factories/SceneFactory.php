<?php

namespace Database\Factories;

use App\Enums\ElapsedBucket;
use App\Enums\ElapsedSource;
use App\Models\Chapter;
use App\Models\Scene;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Scene>
 */
class SceneFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'chapter_id' => Chapter::factory(),
            'number' => fake()->numberBetween(1, 20),
            'pov_mode' => 'third_limited',
            'pov_anchor' => fake()->firstName(),
            'tone' => null,
            'setting' => null,
            'present_characters' => null,
            'elapsed_bucket' => ElapsedBucket::Continuous,
            'elapsed_source' => ElapsedSource::Default,
        ];
    }
}
