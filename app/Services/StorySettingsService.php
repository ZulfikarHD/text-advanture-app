<?php

namespace App\Services;

use App\Enums\LlmRole;
use App\Enums\ModelScope;
use App\Enums\PovMode;
use App\Models\ModelProfile;
use App\Models\Story;
use App\Services\Llm\ModelRoleResolver;
use Illuminate\Support\Facades\DB;

/**
 * Per-story settings management (S-1.2.1).
 *
 * Owns the per-story config that can deviate from global defaults: the default
 * POV (held in the story's `settings` JSON) and per-role model overrides (held
 * as `model_profiles` rows scoped to the story). Resolution order is always
 * per-story override then global default; reads of the model override are served
 * by {@see ModelRoleResolver}. Rubric/tunable overrides are
 * deferred to E5.1 (PH-8), so they are not handled here yet.
 */
class StorySettingsService
{
    /**
     * Resolve the story's default POV, falling back to the engine default.
     *
     * @param  Story  $story  The story whose POV setting is being read.
     * @return PovMode The per-story override, or {@see PovMode::default()} when unset.
     */
    public function resolveDefaultPov(Story $story): PovMode
    {
        $stored = $story->settings['default_pov'] ?? null;

        return PovMode::tryFrom((string) $stored) ?? PovMode::default();
    }

    /**
     * Persist a story's settings as a single atomic operation.
     *
     * Writes the default POV into the `settings` JSON and, per role, either
     * upserts a story-scoped {@see ModelProfile} (when overriding the global
     * default) or deletes it (so the role falls back to the global default).
     *
     * @param  Story  $story  The story to configure (already policy-authorized).
     * @param  array{default_pov: string, roles: list<array{role: string, override: bool, model_slug?: string|null, temperature?: float|int|string|null, max_tokens?: int|string|null, is_active?: bool|null}>}  $data
     */
    public function update(Story $story, array $data): void
    {
        DB::transaction(function () use ($story, $data): void {
            $settings = $story->settings ?? [];
            $settings['default_pov'] = $data['default_pov'];
            $story->update(['settings' => $settings]);

            foreach ($data['roles'] as $row) {
                $this->syncRoleOverride($story, $row);
            }
        });
    }

    /**
     * Upsert or remove the story-scoped override for one engine role.
     *
     * @param  Story  $story  The story owning the override.
     * @param  array{role: string, override: bool, model_slug?: string|null, temperature?: float|int|string|null, max_tokens?: int|string|null, is_active?: bool|null}  $row
     */
    private function syncRoleOverride(Story $story, array $row): void
    {
        $role = LlmRole::from($row['role']);

        $identity = [
            'scope' => ModelScope::Story,
            'story_id' => $story->getKey(),
            'role' => $role,
        ];

        // No override → drop any existing story row so the global default wins.
        if (! ($row['override'] ?? false)) {
            ModelProfile::query()->where($identity)->delete();

            return;
        }

        ModelProfile::updateOrCreate($identity, [
            'model_slug' => $row['model_slug'],
            'params' => [
                'temperature' => (float) $row['temperature'],
                'max_tokens' => (int) $row['max_tokens'],
            ],
            'is_active' => (bool) ($row['is_active'] ?? true),
        ]);
    }
}
