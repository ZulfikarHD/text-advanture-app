<?php

namespace Database\Factories;

use App\Enums\LlmRole;
use App\Enums\ModelScope;
use App\Models\ModelProfile;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ModelProfile>
 */
class ModelProfileFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'scope' => ModelScope::Global,
            'story_id' => null,
            'role' => LlmRole::NarratorProse,
            'model_slug' => 'anthropic/claude-sonnet-4',
            'params' => ['temperature' => 0.7, 'max_tokens' => 2048],
            'is_active' => true,
        ];
    }
}
