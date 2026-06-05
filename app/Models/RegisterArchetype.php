<?php

namespace App\Models;

use Database\Factories\RegisterArchetypeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * RegisterArchetype - a shared, reusable conversational-grammar skeleton
 * (ADR 0006).
 *
 * Global library row (no story scope) defined over the fixed canonical
 * dimension set; a {@see Register} may instantiate from one via its
 * `archetype_id` FK. Seeded app-wide in Sprint 6 (S-6.1.1).
 *
 * @property int $id
 * @property string $slug
 * @property string $name
 * @property array<string, mixed> $dimensions
 * @property string|null $description
 */
#[Fillable(['slug', 'name', 'dimensions', 'description'])]
class RegisterArchetype extends Model
{
    /** @use HasFactory<RegisterArchetypeFactory> */
    use HasFactory;

    /**
     * Registers instantiated from this archetype.
     *
     * @return HasMany<Register, $this>
     */
    public function registers(): HasMany
    {
        return $this->hasMany(Register::class, 'archetype_id');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'dimensions' => 'array',
        ];
    }
}
