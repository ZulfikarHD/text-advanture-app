<?php

namespace App\Exceptions\Llm;

use App\Enums\LlmRole;

/**
 * Thrown when an engine role has no model profile to resolve to (ADR 0017 §2).
 *
 * A role resolves through per-story override then global default; if neither
 * exists (e.g. before the Sprint 6 seeder runs, or after a misconfiguration),
 * the engine must not guess a model - it fails closed with this error.
 */
class UnresolvedModelRoleException extends LlmException
{
    /**
     * Build the exception for a role that could not be resolved to a model.
     */
    public static function for(LlmRole $role, ?int $storyId = null): self
    {
        $scope = $storyId === null ? 'globally' : "for story #{$storyId} or globally";

        return new self("No active model profile is configured {$scope} for role [{$role->value}].");
    }
}
