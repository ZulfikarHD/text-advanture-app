<?php

namespace Database\Factories;

use App\Enums\Axis;
use App\Enums\DeltaChannel;
use App\Enums\DeltaDirection;
use App\Enums\DeltaSource;
use App\Models\AxisDelta;
use App\Models\RelationshipEdge;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AxisDelta>
 */
class AxisDeltaFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'relationship_edge_id' => RelationshipEdge::factory(),
            'axis' => Axis::Affection,
            'direction' => DeltaDirection::Up,
            'magnitude' => 5.00,
            'channel' => DeltaChannel::Drift,
            'trigger' => 'kindness',
            'confidence' => 0.80,
            'value_before' => 0,
            'value_after' => 5,
            'source' => DeltaSource::Appraisal,
            'review_item_id' => null,
        ];
    }
}
