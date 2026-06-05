<?php

namespace Database\Factories;

use App\Enums\BlockAgent;
use App\Enums\BlockSection;
use App\Models\PromptBlock;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PromptBlock>
 */
class PromptBlockFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $key = fake()->unique()->regexify('[A-Z_]{5,12}');

        return [
            'key' => $key,
            'agent' => BlockAgent::Both,
            'section' => BlockSection::System,
            'label' => '['.$key.']',
            'purpose' => fake()->sentence(),
            'source_producers' => [],
            'compile_instruction' => null,
            'leak_rules' => ['none'],
            'order_index' => fake()->numberBetween(0, 20),
            'is_active' => true,
        ];
    }
}
