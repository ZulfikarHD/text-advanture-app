<?php

namespace Database\Factories;

use App\Enums\AwarenessMode;
use App\Enums\Axis;
use App\Models\EdgeAxis;
use App\Models\RelationshipEdge;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EdgeAxis>
 */
class EdgeAxisFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'relationship_edge_id' => RelationshipEdge::factory(),
            'axis' => Axis::Affection,
            'value' => 0,
            'awareness_mode' => AwarenessMode::Auto,
            'soft_floor' => -80,
            'soft_cap' => 80,
            'hard_floor' => -100,
            'hard_cap' => 100,
            'gain_rate' => 1.00,
            'loss_rate' => 1.50,
            'peak_up' => 0,
            'peak_down' => 0,
            'baseline' => 0,
            'latch_threshold' => null,
            'scar' => null,
        ];
    }
}
