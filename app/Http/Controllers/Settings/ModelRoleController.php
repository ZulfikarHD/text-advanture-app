<?php

namespace App\Http\Controllers\Settings;

use App\Enums\LlmRole;
use App\Enums\ModelScope;
use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\ModelRoleUpdateRequest;
use App\Models\ModelProfile;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Manages the global role -> model mapping (S-5.2.2, ADR 0017 §2).
 *
 * Calls are routed by role, never a hard-coded slug; this screen edits the
 * app-wide global defaults so an author can tier strong/cheap models without
 * code changes. Per-story overrides are out of scope here (they need a story
 * context that arrives with story management in Phase 2). Defaults are seeded
 * in Sprint 6, so the screen also supports configuring roles from empty.
 */
class ModelRoleController extends Controller
{
    /**
     * Show the model-role settings screen with every engine role.
     */
    public function edit(): Response
    {
        $profiles = ModelProfile::query()
            ->where('scope', ModelScope::Global)
            ->whereNull('story_id')
            ->get()
            ->keyBy(fn (ModelProfile $profile): string => $profile->role->value);

        return Inertia::render('engine/ModelRoles', [
            'roles' => $this->presentRoles($profiles),
        ]);
    }

    /**
     * Upsert the global model profile for each submitted role.
     */
    public function update(ModelRoleUpdateRequest $request): RedirectResponse
    {
        foreach ($request->validated('roles') as $row) {
            ModelProfile::updateOrCreate(
                [
                    'scope' => ModelScope::Global,
                    'story_id' => null,
                    'role' => LlmRole::from($row['role']),
                ],
                [
                    'model_slug' => $row['model_slug'],
                    'params' => [
                        'temperature' => (float) $row['temperature'],
                        'max_tokens' => (int) $row['max_tokens'],
                    ],
                    'is_active' => (bool) $row['is_active'],
                ],
            );
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Model roles saved.')]);

        return to_route('model-roles.edit');
    }

    /**
     * Build the per-role view rows, merging any stored global profile.
     *
     * @param  Collection<string, ModelProfile>  $profiles  Existing global profiles keyed by role value.
     * @return list<array{role: string, label: string, description: string, modelSlug: string, temperature: float, maxTokens: int, isActive: bool, configured: bool}>
     */
    private function presentRoles(Collection $profiles): array
    {
        return collect(LlmRole::cases())
            ->map(function (LlmRole $role) use ($profiles): array {
                $profile = $profiles->get($role->value);

                return [
                    'role' => $role->value,
                    'label' => $role->label(),
                    'description' => $role->description(),
                    'modelSlug' => $profile?->model_slug ?? '',
                    'temperature' => (float) ($profile?->params['temperature'] ?? 0.7),
                    'maxTokens' => (int) ($profile?->params['max_tokens'] ?? 2048),
                    'isActive' => (bool) ($profile?->is_active ?? true),
                    'configured' => $profile !== null,
                ];
            })
            ->all();
    }
}
