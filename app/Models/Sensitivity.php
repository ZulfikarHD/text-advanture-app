<?php

namespace App\Models;

use App\Enums\SensitivityChannel;
use App\Enums\SensitivityTarget;
use App\Enums\SensitivityWeight;
use Database\Factories\SensitivityFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Sensitivity - an authored appraisal amplifier / special-case (ADR 0005).
 *
 * Unique per `(character_id, slug)`. `detect` is a natural-language matcher the
 * appraiser LLM evaluates. Authoring-realm child of {@see Character}.
 *
 * @property int $id
 * @property int $character_id
 * @property string $slug
 * @property string $detect
 * @property SensitivityTarget $target
 * @property array<string, mixed> $axes
 * @property SensitivityWeight $weight
 * @property SensitivityChannel $channel
 */
#[Fillable([
    'character_id',
    'slug',
    'detect',
    'target',
    'axes',
    'weight',
    'channel',
])]
class Sensitivity extends Model
{
    /** @use HasFactory<SensitivityFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<Character, $this>
     */
    public function character(): BelongsTo
    {
        return $this->belongsTo(Character::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'target' => SensitivityTarget::class,
            'axes' => 'array',
            'weight' => SensitivityWeight::class,
            'channel' => SensitivityChannel::class,
        ];
    }
}
