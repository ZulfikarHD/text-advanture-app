<?php

namespace App\Models;

use App\Enums\SensitivityChannel;
use App\Enums\SensitivityWeight;
use Database\Factories\UniversalPriorFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * UniversalPrior - a shared appraisal baseline reaction (ADR 0005).
 *
 * Global library row (no story scope) giving appraisal a common starting point
 * (insult, kindness, threat, broken promise) before character-specific
 * sensitivities apply. Seeded app-wide in Sprint 6 (S-6.1.1).
 *
 * @property int $id
 * @property string $slug
 * @property string $detect
 * @property array<string, mixed> $axes
 * @property SensitivityWeight $default_weight
 * @property SensitivityChannel $channel
 */
#[Fillable(['slug', 'detect', 'axes', 'default_weight', 'channel'])]
class UniversalPrior extends Model
{
    /** @use HasFactory<UniversalPriorFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'axes' => 'array',
            'default_weight' => SensitivityWeight::class,
            'channel' => SensitivityChannel::class,
        ];
    }
}
