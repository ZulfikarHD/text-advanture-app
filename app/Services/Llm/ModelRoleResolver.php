<?php

namespace App\Services\Llm;

use App\Enums\LlmRole;
use App\Enums\ModelScope;
use App\Exceptions\Llm\UnresolvedModelRoleException;
use App\Models\ModelProfile;
use App\Models\Story;

/**
 * Resolves an engine {@see LlmRole} to the model profile that serves it (ADR 0017 §2).
 *
 * Calls are routed by role, never a hard-coded slug. Resolution order is a
 * per-story override first, then the global default; only `is_active` profiles
 * are considered. When nothing matches the role fails closed via
 * {@see UnresolvedModelRoleException} rather than the engine guessing a model.
 */
class ModelRoleResolver
{
    /**
     * Resolve the active model profile for a role, preferring a per-story override.
     *
     * @param  LlmRole  $role  The engine role making the call.
     * @param  Story|null  $story  The story in play, whose override (if any) wins over the global default.
     * @return ModelProfile The resolved profile carrying the model slug + params.
     *
     * @throws UnresolvedModelRoleException When no active profile exists for the role.
     */
    public function resolve(LlmRole $role, ?Story $story = null): ModelProfile
    {
        // Per-story override takes precedence over the global default.
        if ($story !== null) {
            $override = ModelProfile::query()
                ->where('scope', ModelScope::Story)
                ->where('story_id', $story->getKey())
                ->where('role', $role)
                ->where('is_active', true)
                ->first();

            if ($override !== null) {
                return $override;
            }
        }

        $global = ModelProfile::query()
            ->where('scope', ModelScope::Global)
            ->whereNull('story_id')
            ->where('role', $role)
            ->where('is_active', true)
            ->first();

        if ($global === null) {
            throw UnresolvedModelRoleException::for($role, $story?->getKey());
        }

        return $global;
    }
}
