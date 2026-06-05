<?php

namespace App\Models;

use Database\Factories\CharacterArchetypeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * CharacterArchetype - a seedable whole-character shape (ADR 0018).
 *
 * Global library row (no story scope) bundling base opacity, suggested live
 * axes, default disposition priors, registers, sensitivities, and a voice
 * scaffold. Selecting one seeds character creation as a starting point, never
 * a constraint - every field stays editable. Seeded in Sprint 6 (S-6.1.2).
 *
 * @property int $id
 * @property string $slug
 * @property string $name
 * @property string|null $description
 * @property int $base_opacity
 * @property array<int, string> $suggested_live_axes
 * @property array<string, mixed> $default_disposition_priors
 * @property array<int|string, mixed> $default_registers
 * @property array<int|string, mixed> $default_sensitivities
 * @property array<string, mixed>|null $voice_scaffold
 */
#[Fillable([
    'slug',
    'name',
    'description',
    'base_opacity',
    'suggested_live_axes',
    'default_disposition_priors',
    'default_registers',
    'default_sensitivities',
    'voice_scaffold',
])]
class CharacterArchetype extends Model
{
    /** @use HasFactory<CharacterArchetypeFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'base_opacity' => 'integer',
            'suggested_live_axes' => 'array',
            'default_disposition_priors' => 'array',
            'default_registers' => 'array',
            'default_sensitivities' => 'array',
            'voice_scaffold' => 'array',
        ];
    }
}
