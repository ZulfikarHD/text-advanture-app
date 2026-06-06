<?php

namespace Tests\Feature\Settings;

use App\Models\User;
use App\Services\ProviderCredentialService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Page + flow tests for the provider key settings screen (S-5.1.1).
 *
 * Renders for an authenticated user, exposes only a masked key (never the raw
 * value) in its props, stores and removes the key through the routes, and
 * redirects guests to login.
 */
class ProviderSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_provider_page_is_displayed_with_no_key(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('provider.edit'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('engine/Provider')
                ->where('credential', null)
                ->where('provider', 'openrouter')
            );
    }

    public function test_provider_page_exposes_only_the_masked_key(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        (new ProviderCredentialService)->store($user, 'sk-or-v1-secretkey-7788');

        $this->get(route('provider.edit'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('engine/Provider')
                ->where('credential.maskedKey', fn (?string $masked) => $masked !== null && str_contains($masked, '7788'))
                ->missing('credential.api_key')
            );
    }

    public function test_guests_are_redirected_to_login(): void
    {
        $this->get(route('provider.edit'))->assertRedirect(route('login'));
    }

    public function test_a_user_can_save_a_provider_key(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->from(route('provider.edit'))
            ->put(route('provider.update'), [
                'api_key' => 'sk-or-v1-brandnewkey-4321',
                'base_url' => 'https://openrouter.ai/api/v1',
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('provider.edit'));

        $this->assertSame('4321', (new ProviderCredentialService)->for($user->fresh())?->last_four);
    }

    public function test_a_user_can_remove_their_provider_key(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        (new ProviderCredentialService)->store($user, 'sk-or-v1-deletekey-0000');

        $this->from(route('provider.edit'))
            ->delete(route('provider.destroy'))
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('provider.edit'));

        $this->assertNull((new ProviderCredentialService)->for($user->fresh()));
    }
}
