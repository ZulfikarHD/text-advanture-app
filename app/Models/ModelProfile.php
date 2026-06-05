<?php

namespace App\Models;

use App\Enums\LlmRole;
use App\Enums\ModelScope;
use Database\Factories\ModelProfileFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * ModelProfile - a role -> model slug + params mapping (ADR 0017 §2).
 *
 * `Global` rows (null `story_id`) are the engine-wide defaults; `Story` rows
 * override a single role for one story. Resolution order is per-story override
 * then global default. Unique per `(scope, story_id, role)`. Seeded with global
 * defaults in Sprint 6 (S-6.1.4).
 *
 * @property int $id
 * @property ModelScope $scope
 * @property int|null $story_id
 * @property LlmRole $role
 * @property string $model_slug
 * @property array<string, mixed>|null $params
 * @property bool $is_active
 */
#[Fillable(['scope', 'story_id', 'role', 'model_slug', 'params', 'is_active'])]
class ModelProfile extends Model
{
    /** @use HasFactory<ModelProfileFactory> */
    use HasFactory;

    /**
     * The story this profile overrides for, or null for a global default.
     *
     * @return BelongsTo<Story, $this>
     */
    public function story(): BelongsTo
    {
        return $this->belongsTo(Story::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'scope' => ModelScope::class,
            'role' => LlmRole::class,
            'params' => 'array',
            'is_active' => 'boolean',
        ];
    }
}
