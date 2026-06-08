<?php

namespace Tests\Feature\Settings;

use App\Models\User;
use App\Services\Llm\ProviderModelCatalog;
use App\Services\ProviderCredentialService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Tests for the provider model-catalog endpoint (S-5.2.2 picker).
 *
 * Lists the models a stored key can reach for the role -> model picker: a valid
 * key returns the normalised, name-sorted catalog; a missing key or an
 * unreachable provider degrades to an empty list (never an error) so the picker
 * falls back to manual entry; and guests are redirected.
 */
class ProviderModelsTest extends TestCase
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
        (new ProviderCredentialService)->store($user, 'sk-or-v1-catalog-1111');

        return $user;
    }

    public function test_it_lists_reachable_models_sorted_by_name(): void
    {
        Http::fake([
            '*/models' => Http::response([
                'data' => [
                    ['id' => 'openai/gpt-4o', 'name' => 'OpenAI: GPT-4o', 'context_length' => 128000],
                    ['id' => 'anthropic/claude-sonnet-4', 'name' => 'Anthropic: Claude Sonnet 4', 'context_length' => 200000],
                ],
            ]),
        ]);

        $this->ownerWithKey();

        $this->getJson(route('provider.models'))
            ->assertOk()
            ->assertJsonCount(2, 'models')
            ->assertJsonPath('models.0.id', 'anthropic/claude-sonnet-4')
            ->assertJsonPath('models.0.name', 'Anthropic: Claude Sonnet 4')
            ->assertJsonPath('models.0.contextLength', 200000);
    }

    public function test_it_falls_back_to_the_id_and_null_context_when_a_model_lacks_metadata(): void
    {
        Http::fake([
            '*/models' => Http::response([
                'data' => [
                    ['id' => 'meta-llama/llama-3'],
                ],
            ]),
        ]);

        $this->ownerWithKey();

        $this->getJson(route('provider.models'))
            ->assertOk()
            ->assertJsonPath('models.0.name', 'meta-llama/llama-3')
            ->assertJsonPath('models.0.contextLength', null);
    }

    public function test_it_returns_an_empty_list_when_no_key_is_stored(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $this->getJson(route('provider.models'))
            ->assertOk()
            ->assertExactJson(['models' => []]);
    }

    public function test_it_returns_an_empty_list_when_the_provider_is_unreachable(): void
    {
        Http::fake([
            '*/models' => Http::response(['error' => ['message' => 'Invalid API key']], 401),
        ]);

        $this->ownerWithKey();

        $this->getJson(route('provider.models'))
            ->assertOk()
            ->assertExactJson(['models' => []]);
    }

    public function test_guests_cannot_list_models(): void
    {
        $this->get(route('provider.models'))->assertRedirect(route('login'));
    }

    public function test_replacing_the_key_clears_the_cached_catalog(): void
    {
        Http::fake([
            '*/models' => Http::response([
                'data' => [['id' => 'anthropic/claude-sonnet-4', 'name' => 'Claude']],
            ]),
        ]);

        $user = $this->ownerWithKey();
        $cacheKey = "provider-models:{$user->id}:openrouter";

        // Prime the cache with the catalog fetched under the current key.
        app(ProviderModelCatalog::class)->for($user);
        $this->assertTrue(Cache::has($cacheKey));

        $this->put(route('provider.update'), ['api_key' => 'sk-or-v1-rotated-2222'])
            ->assertRedirect();

        $this->assertFalse(Cache::has($cacheKey));
    }
}
