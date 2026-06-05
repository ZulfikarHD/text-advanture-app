<?php

namespace Tests\Feature\Auth;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Laravel\Fortify\Features;
use Tests\TestCase;

/**
 * Configurable self-registration (S-2.2.3).
 *
 * The Fortify register routes stay registered (so the typed `register` route
 * remains available to the build); the deployment toggle gates them at the
 * application layer. When closed, the page and submission return 404 while
 * sign-in stays available.
 */
class RegistrationToggleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->skipUnlessFortifyHas(Features::registration());
    }

    public function test_register_page_is_unavailable_when_registration_is_disabled(): void
    {
        config(['app.registration_enabled' => false]);

        $this->get(route('register'))->assertNotFound();
    }

    public function test_register_submission_is_rejected_when_registration_is_disabled(): void
    {
        config(['app.registration_enabled' => false]);

        $this->post(route('register.store'), [
            'name' => 'Blocked User',
            'email' => 'blocked@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ])->assertNotFound();

        $this->assertGuest();
        $this->assertDatabaseMissing('users', ['email' => 'blocked@example.com']);
    }

    public function test_login_remains_available_when_registration_is_disabled(): void
    {
        config(['app.registration_enabled' => false]);

        $this->get(route('login'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('auth/Login')
                ->where('canRegister', false),
            );
    }

    public function test_register_page_is_available_when_registration_is_enabled(): void
    {
        config(['app.registration_enabled' => true]);

        $this->get(route('register'))->assertOk();
    }
}
