<?php

namespace Tests\Feature\Stories;

use App\Enums\LlmRole;
use App\Enums\ModelScope;
use App\Models\ModelProfile;
use App\Models\Story;
use App\Models\User;
use App\Services\Llm\ModelRoleResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Tests\TestCase;

/**
 * Feature tests for per-story settings (S-1.2.1).
 *
 * Covers: rendering for the owner, owner-scoped 404s, default-POV persistence,
 * model-role override create/clear with per-story → global resolution, and the
 * override validation rules.
 */
class StorySettingsTest extends TestCase
{
    use RefreshDatabase;

    // --- Rendering & ownership ---

    public function test_owner_can_open_story_settings(): void
    {
        $user = User::factory()->create();
        $story = Story::factory()->create(['user_id' => $user->id, 'slug' => 'my-story']);

        $response = $this->actingAs($user)->get(route('stories.settings.edit', $story));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('stories/Settings')
            ->where('story.slug', 'my-story')
            ->has('roles', count(LlmRole::cases()))
            ->has('povOptions')
        );
    }

    public function test_settings_edit_404s_on_foreign_story(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        Story::factory()->create(['user_id' => $other->id, 'slug' => 'theirs']);

        $response = $this->actingAs($owner)->get(route('stories.settings.edit', ['story' => 'theirs']));

        $response->assertNotFound();
    }

    public function test_guests_cannot_open_story_settings(): void
    {
        $story = Story::factory()->create();

        $response = $this->get(route('stories.settings.edit', $story));

        $response->assertRedirect(route('login'));
    }

    // --- Default POV ---

    public function test_update_persists_default_pov(): void
    {
        $user = User::factory()->create();
        $story = Story::factory()->create(['user_id' => $user->id]);

        $this->actingAs($user)->put(
            route('stories.settings.update', $story),
            $this->settingsPayload(pov: 'first_person'),
        );

        $this->assertSame('first_person', $story->fresh()->settings['default_pov']);
    }

    public function test_default_pov_saves_on_its_own_without_role_rows(): void
    {
        $user = User::factory()->create();
        $story = Story::factory()->create(['user_id' => $user->id]);

        $this->actingAs($user)
            ->from(route('stories.settings.edit', $story))
            ->put(route('stories.settings.update', $story), [
                'default_pov' => 'first_person',
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame('first_person', $story->fresh()->settings['default_pov']);
    }

    public function test_invalid_default_pov_is_rejected(): void
    {
        $user = User::factory()->create();
        $story = Story::factory()->create(['user_id' => $user->id]);

        $response = $this->actingAs($user)->put(
            route('stories.settings.update', $story),
            $this->settingsPayload(pov: 'sideways'),
        );

        $response->assertSessionHasErrors('default_pov');
    }

    // --- Model-role overrides ---

    public function test_update_creates_a_story_scoped_model_override(): void
    {
        $user = User::factory()->create();
        $story = Story::factory()->create(['user_id' => $user->id]);

        $this->actingAs($user)->put(
            route('stories.settings.update', $story),
            $this->settingsPayload(roleOverrides: [
                LlmRole::NarratorProse->value => [
                    'override' => true,
                    'model_slug' => 'story/narrator',
                ],
            ]),
        );

        $this->assertDatabaseHas('model_profiles', [
            'scope' => ModelScope::Story->value,
            'story_id' => $story->id,
            'role' => LlmRole::NarratorProse->value,
            'model_slug' => 'story/narrator',
        ]);
    }

    public function test_a_single_role_override_saves_without_a_pov(): void
    {
        $user = User::factory()->create();
        $story = Story::factory()->create(['user_id' => $user->id]);

        $this->actingAs($user)
            ->from(route('stories.settings.edit', $story))
            ->put(route('stories.settings.update', $story), [
                'roles' => [[
                    'role' => LlmRole::NarratorProse->value,
                    'override' => true,
                    'model_slug' => 'story/narrator',
                    'temperature' => 0.5,
                    'max_tokens' => 1000,
                    'is_active' => true,
                ]],
            ])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('model_profiles', [
            'scope' => ModelScope::Story->value,
            'story_id' => $story->id,
            'role' => LlmRole::NarratorProse->value,
            'model_slug' => 'story/narrator',
        ]);
    }

    public function test_saving_one_role_leaves_other_overrides_untouched(): void
    {
        $user = User::factory()->create();
        $story = Story::factory()->create(['user_id' => $user->id]);

        ModelProfile::factory()->create([
            'scope' => ModelScope::Story,
            'story_id' => $story->id,
            'role' => LlmRole::BeatJudge,
            'model_slug' => 'story/beat-judge',
        ]);

        $this->actingAs($user)
            ->put(route('stories.settings.update', $story), [
                'roles' => [[
                    'role' => LlmRole::NarratorProse->value,
                    'override' => true,
                    'model_slug' => 'story/narrator',
                    'temperature' => 0.5,
                    'max_tokens' => 1000,
                    'is_active' => true,
                ]],
            ])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('model_profiles', [
            'story_id' => $story->id,
            'role' => LlmRole::BeatJudge->value,
            'model_slug' => 'story/beat-judge',
        ]);
        $this->assertDatabaseHas('model_profiles', [
            'story_id' => $story->id,
            'role' => LlmRole::NarratorProse->value,
            'model_slug' => 'story/narrator',
        ]);
    }

    public function test_story_override_takes_precedence_over_global(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $story = Story::factory()->create(['user_id' => $user->id]);

        ModelProfile::factory()->create([
            'scope' => ModelScope::Global,
            'story_id' => null,
            'role' => LlmRole::NarratorProse,
            'model_slug' => 'global/narrator',
        ]);

        $this->put(
            route('stories.settings.update', $story),
            $this->settingsPayload(roleOverrides: [
                LlmRole::NarratorProse->value => [
                    'override' => true,
                    'model_slug' => 'story/narrator',
                ],
            ]),
        );

        $resolved = app(ModelRoleResolver::class)->resolve(LlmRole::NarratorProse, $story);

        $this->assertSame('story/narrator', $resolved->model_slug);
    }

    public function test_clearing_an_override_removes_the_story_profile(): void
    {
        $user = User::factory()->create();
        $story = Story::factory()->create(['user_id' => $user->id]);

        ModelProfile::factory()->create([
            'scope' => ModelScope::Story,
            'story_id' => $story->id,
            'role' => LlmRole::NarratorProse,
            'model_slug' => 'story/narrator',
        ]);

        $this->actingAs($user)->put(
            route('stories.settings.update', $story),
            $this->settingsPayload(roleOverrides: [
                LlmRole::NarratorProse->value => ['override' => false],
            ]),
        );

        $this->assertDatabaseMissing('model_profiles', [
            'scope' => ModelScope::Story->value,
            'story_id' => $story->id,
            'role' => LlmRole::NarratorProse->value,
        ]);
    }

    public function test_override_requires_a_model_slug(): void
    {
        $user = User::factory()->create();
        $story = Story::factory()->create(['user_id' => $user->id]);

        $response = $this->actingAs($user)->put(
            route('stories.settings.update', $story),
            $this->settingsPayload(roleOverrides: [
                LlmRole::NarratorProse->value => [
                    'override' => true,
                    'model_slug' => '',
                ],
            ]),
        );

        $response->assertSessionHasErrors('roles.0.model_slug');
    }

    public function test_settings_update_404s_on_foreign_story(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        Story::factory()->create(['user_id' => $other->id, 'slug' => 'theirs']);

        $response = $this->actingAs($owner)->put(
            route('stories.settings.update', ['story' => 'theirs']),
            $this->settingsPayload(),
        );

        $response->assertNotFound();
    }

    /**
     * Build a settings payload for every engine role, optionally overriding rows.
     *
     * @param  array<string, array<string, mixed>>  $roleOverrides  Per-role field overrides keyed by role value.
     * @param  string  $pov  The default POV value to submit.
     * @return array{default_pov: string, roles: list<array<string, mixed>>}
     */
    private function settingsPayload(array $roleOverrides = [], string $pov = 'third_limited'): array
    {
        $roles = (new Collection(LlmRole::cases()))
            ->map(fn (LlmRole $role): array => array_merge([
                'role' => $role->value,
                'override' => false,
                'model_slug' => 'anthropic/claude-sonnet-4',
                'temperature' => 0.7,
                'max_tokens' => 2048,
                'is_active' => true,
            ], $roleOverrides[$role->value] ?? []))
            ->all();

        return [
            'default_pov' => $pov,
            'roles' => $roles,
        ];
    }
}
