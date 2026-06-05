<?php

namespace App\Http\Controllers\Stories;

use App\Enums\LlmRole;
use App\Enums\ModelScope;
use App\Enums\PovMode;
use App\Http\Controllers\Controller;
use App\Http\Requests\Stories\UpdateStorySettingsRequest;
use App\Models\ModelProfile;
use App\Models\Story;
use App\Services\Llm\ModelRoleResolver;
use App\Services\StorySettingsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Per-story settings — default POV + model-role overrides (S-1.2.1).
 *
 * Lets a story deviate from the global defaults. The default POV lives in the
 * story's `settings` JSON; each engine role can override the global model
 * profile with a story-scoped one. Resolution order is per-story override →
 * global default (resolved by {@see ModelRoleResolver}).
 * Rubric/tunable overrides are deferred to E5.1 (PH-8).
 */
class StorySettingsController extends Controller
{
    public function __construct(private readonly StorySettingsService $settings) {}

    /**
     * Render the per-story settings screen, merging global defaults + overrides.
     */
    public function edit(Story $story): Response
    {
        Gate::authorize('view', $story);

        $globals = ModelProfile::query()
            ->where('scope', ModelScope::Global)
            ->whereNull('story_id')
            ->get()
            ->keyBy(fn (ModelProfile $profile): string => $profile->role->value);

        $overrides = ModelProfile::query()
            ->where('scope', ModelScope::Story)
            ->where('story_id', $story->getKey())
            ->get()
            ->keyBy(fn (ModelProfile $profile): string => $profile->role->value);

        return Inertia::render('stories/Settings', [
            'story' => [
                'id' => $story->id,
                'slug' => $story->slug,
                'title' => $story->title,
            ],
            'defaultPov' => $this->settings->resolveDefaultPov($story)->value,
            'povOptions' => $this->presentPovOptions(),
            'roles' => $this->presentRoles($globals, $overrides),
        ]);
    }

    /**
     * Persist the story's settings (default POV + model-role overrides).
     */
    public function update(UpdateStorySettingsRequest $request, Story $story): RedirectResponse
    {
        Gate::authorize('update', $story);

        $this->settings->update($story, $request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Story settings saved.')]);

        return to_route('stories.settings.edit', $story);
    }

    /**
     * Build the POV select options from the enum.
     *
     * @return list<array{value: string, label: string, description: string}>
     */
    private function presentPovOptions(): array
    {
        return collect(PovMode::cases())
            ->map(fn (PovMode $pov): array => [
                'value' => $pov->value,
                'label' => $pov->label(),
                'description' => $pov->description(),
            ])
            ->all();
    }

    /**
     * Build the per-role rows, merging the global default with any story override.
     *
     * When a role is overridden the row carries the override's values; otherwise
     * it pre-fills from the global default so toggling the override on starts
     * from a sane baseline. `global` always carries the fallback for display.
     *
     * @param  Collection<string, ModelProfile>  $globals  Global profiles keyed by role value.
     * @param  Collection<string, ModelProfile>  $overrides  Story overrides keyed by role value.
     * @return list<array{role: string, label: string, description: string, override: bool, modelSlug: string, temperature: float, maxTokens: int, isActive: bool, global: array{modelSlug: string, temperature: float, maxTokens: int, isActive: bool, configured: bool}}>
     */
    private function presentRoles(Collection $globals, Collection $overrides): array
    {
        return collect(LlmRole::cases())
            ->map(function (LlmRole $role) use ($globals, $overrides): array {
                $global = $globals->get($role->value);
                $override = $overrides->get($role->value);

                $globalSlug = $global?->model_slug ?? '';
                $globalTemp = (float) ($global?->params['temperature'] ?? 0.7);
                $globalMaxTokens = (int) ($global?->params['max_tokens'] ?? 2048);
                $globalActive = (bool) ($global?->is_active ?? true);

                return [
                    'role' => $role->value,
                    'label' => $role->label(),
                    'description' => $role->description(),
                    'override' => $override !== null,
                    'modelSlug' => $override?->model_slug ?? $globalSlug,
                    'temperature' => (float) ($override?->params['temperature'] ?? $globalTemp),
                    'maxTokens' => (int) ($override?->params['max_tokens'] ?? $globalMaxTokens),
                    'isActive' => (bool) ($override?->is_active ?? $globalActive),
                    'global' => [
                        'modelSlug' => $globalSlug,
                        'temperature' => $globalTemp,
                        'maxTokens' => $globalMaxTokens,
                        'isActive' => $globalActive,
                        'configured' => $global !== null,
                    ],
                ];
            })
            ->all();
    }
}
