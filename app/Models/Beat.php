<?php

namespace App\Models;

use Database\Factories\BeatFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Beat - an ordered beat within a scene (ADR 0015).
 *
 * `intent` is omniscient author-side text that is never injected raw.
 * Authoring-realm child of {@see Scene}; unique per `(scene_id, number)`.
 *
 * @property int $id
 * @property int $scene_id
 * @property int $number
 * @property string $intent
 * @property string $goal
 * @property int $word_budget
 * @property int|null $nudge_target_character_id
 */
#[Fillable([
    'scene_id',
    'number',
    'intent',
    'goal',
    'word_budget',
    'nudge_target_character_id',
])]
class Beat extends Model
{
    /** @use HasFactory<BeatFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<Scene, $this>
     */
    public function scene(): BelongsTo
    {
        return $this->belongsTo(Scene::class);
    }

    /**
     * The character (if any) a nudge in this beat is framed onto.
     *
     * @return BelongsTo<Character, $this>
     */
    public function nudgeTarget(): BelongsTo
    {
        return $this->belongsTo(Character::class, 'nudge_target_character_id');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'number' => 'integer',
            'word_budget' => 'integer',
        ];
    }
}
