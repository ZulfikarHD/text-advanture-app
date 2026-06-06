<?php

namespace Tests\Feature\Settings;

use App\Enums\LlmRole;
use App\Models\ModelProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Page + flow tests for the model-role settings screen (S-5.2.2).
 *
 * Renders every engine role, upserts the global profile on save, validates the
 * model slug, and redirects guests to login.
 */
class ModelRoleSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_page_renders_with_every_engine_role(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('model-roles.edit'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('engine/ModelRoles')
                ->has('roles', count(LlmRole::cases()))
            );
    }

    public function test_saving_upserts_the_global_profile_for_a_role(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->from(route('model-roles.edit'))
            ->put(route('model-roles.update'), [
                'roles' => [[
                    'role' => LlmRole::NarratorProse->value,
                    'model_slug' => 'anthropic/claude-opus-4',
                    'temperature' => 0.6,
                    'max_tokens' => 4096,
                    'is_active' => true,
                ]],
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('model-roles.edit'));

        $this->assertDatabaseHas('model_profiles', [
            'scope' => 'global',
            'story_id' => null,
            'role' => LlmRole::NarratorProse->value,
            'model_slug' => 'anthropic/claude-opus-4',
            'is_active' => true,
        ]);

        $profile = ModelProfile::query()->where('role', LlmRole::NarratorProse)->firstOrFail();
        $this->assertSame(0.6, $profile->params['temperature']);
        $this->assertSame(4096, $profile->params['max_tokens']);
    }

    public function test_a_missing_model_slug_is_rejected(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->from(route('model-roles.edit'))
            ->put(route('model-roles.update'), [
                'roles' => [[
                    'role' => LlmRole::NarratorProse->value,
                    'model_slug' => '',
                    'temperature' => 0.6,
                    'max_tokens' => 4096,
                    'is_active' => true,
                ]],
            ])
            ->assertSessionHasErrors('roles.0.model_slug');
    }

    public function test_guests_are_redirected_to_login(): void
    {
        $this->get(route('model-roles.edit'))->assertRedirect(route('login'));
    }
}
