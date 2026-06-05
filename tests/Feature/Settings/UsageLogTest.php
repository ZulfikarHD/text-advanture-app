<?php

namespace Tests\Feature\Settings;

use App\Models\LlmCall;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Page + isolation tests for the usage / activity log (S-5.3.1).
 *
 * The call list is a deferred prop loaded on a follow-up request; it is strictly
 * owner-scoped (a user never sees another owner's calls) and exposes the
 * provider cost in USD micro-units. Guests are redirected to login.
 */
class UsageLogTest extends TestCase
{
    use RefreshDatabase;

    public function test_usage_page_renders_with_a_deferred_call_list(): void
    {
        $user = User::factory()->create();
        LlmCall::factory()->forOwner($user)->count(2)->create();

        $this->actingAs($user)
            ->get(route('usage.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('settings/Usage')
                ->missing('calls')
                ->loadDeferredProps(fn (Assert $reload) => $reload
                    ->has('calls.data', 2)
                    ->etc()
                )
            );
    }

    public function test_a_user_never_sees_another_owners_calls(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();

        LlmCall::factory()->forOwner($owner)->count(2)->create();
        LlmCall::factory()->forOwner($other)->count(3)->create();

        $this->actingAs($owner)
            ->get(route('usage.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->loadDeferredProps(fn (Assert $reload) => $reload
                    ->has('calls.data', 2)
                    ->where('calls.total', 2)
                    ->etc()
                )
            );
    }

    public function test_cost_is_exposed_as_usd_micro_units(): void
    {
        $user = User::factory()->create();
        LlmCall::factory()->forOwner($user)->create(['cost_micros_usd' => 1234]);

        $this->actingAs($user)
            ->get(route('usage.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->loadDeferredProps(fn (Assert $reload) => $reload
                    ->where('calls.data.0.costMicrosUsd', 1234)
                    ->etc()
                )
            );
    }

    public function test_guests_are_redirected_to_login(): void
    {
        $this->get(route('usage.index'))->assertRedirect(route('login'));
    }
}
