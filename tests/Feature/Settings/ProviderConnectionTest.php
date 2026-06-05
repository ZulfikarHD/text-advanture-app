<?php

namespace Tests\Feature\Settings;

use App\Models\User;
use App\Services\ProviderCredentialService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Tests for the provider connection test endpoint (S-5.1.2).
 *
 * A valid key lists reachable models; an invalid key surfaces the provider's
 * reason; a missing key fails gracefully; guests are redirected; the endpoint
 * is rate limited; and the probe never writes to the `llm_calls` log.
 */
class ProviderConnectionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Http::preventStrayRequests();
    }

    private function ownerWithKey(): User
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        (new ProviderCredentialService)->store($user, 'sk-or-v1-conn-1111');

        return $user;
    }

    public function test_connection_test_succeeds_and_lists_models(): void
    {
        Http::fake([
            '*/models' => Http::response([
                'data' => [
                    ['id' => 'anthropic/claude-sonnet-4'],
                    ['id' => 'openai/gpt-4o'],
                ],
            ]),
        ]);

        $this->ownerWithKey();

        $this->postJson(route('provider.test'))
            ->assertOk()
            ->assertJson(['ok' => true, 'reachableModelCount' => 2])
            ->assertJsonPath('sampleModels.0', 'anthropic/claude-sonnet-4');

        $this->assertDatabaseCount('llm_calls', 0);
    }

    public function test_connection_test_reports_an_invalid_key(): void
    {
        Http::fake([
            '*/models' => Http::response(['error' => ['message' => 'Invalid API key']], 401),
        ]);

        $this->ownerWithKey();

        $this->postJson(route('provider.test'))
            ->assertOk()
            ->assertJson(['ok' => false])
            ->assertJsonPath('failureReason', 'Invalid API key');

        $this->assertDatabaseCount('llm_calls', 0);
    }

    public function test_connection_test_without_a_stored_key_fails_gracefully(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $this->postJson(route('provider.test'))
            ->assertOk()
            ->assertJson(['ok' => false])
            ->assertJsonPath('failureReason', fn (string $reason): bool => str_contains($reason, 'No API key'));
    }

    public function test_guests_cannot_test_a_connection(): void
    {
        $this->post(route('provider.test'))->assertRedirect(route('login'));
    }

    public function test_connection_test_is_rate_limited(): void
    {
        Http::fake(['*/models' => Http::response(['data' => []])]);

        $this->ownerWithKey();

        for ($attempt = 0; $attempt < 6; $attempt++) {
            $this->postJson(route('provider.test'))->assertOk();
        }

        $this->postJson(route('provider.test'))->assertStatus(429);
    }
}
