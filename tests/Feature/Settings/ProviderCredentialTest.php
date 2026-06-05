<?php

namespace Tests\Feature\Settings;

use App\Enums\Provider;
use App\Models\ProviderCredential;
use App\Models\User;
use App\Services\ProviderCredentialService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Storage + isolation tests for {@see ProviderCredential} (S-5.1.3).
 *
 * Proves the API key is encrypted at rest, never serialized in plaintext,
 * replaced (not duplicated) on re-save, removable, and strictly owner-scoped so
 * one user can never read another's key.
 */
class ProviderCredentialTest extends TestCase
{
    use RefreshDatabase;

    private function service(): ProviderCredentialService
    {
        return new ProviderCredentialService;
    }

    public function test_api_key_is_encrypted_at_rest(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $credential = $this->service()->store($user, 'sk-or-v1-supersecret-123456');

        $rawStored = DB::table('provider_credentials')->where('id', $credential->id)->value('api_key');

        $this->assertNotSame('sk-or-v1-supersecret-123456', $rawStored);
        $this->assertSame('sk-or-v1-supersecret-123456', $credential->fresh()->api_key);
    }

    public function test_only_a_masked_key_is_serialized(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $credential = $this->service()->store($user, 'sk-or-v1-abcdefgh3456');
        $array = $credential->toArray();

        $this->assertArrayNotHasKey('api_key', $array);
        $this->assertArrayHasKey('masked_key', $array);
        $this->assertStringContainsString('3456', (string) $credential->masked_key);
        $this->assertStringNotContainsString('abcdefgh', (string) json_encode($array));
    }

    public function test_storing_again_replaces_the_existing_key(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $this->service()->store($user, 'sk-or-v1-aaaaaaaa1111');
        $this->service()->store($user, 'sk-or-v1-bbbbbbbb2222');

        $this->assertSame(
            1,
            ProviderCredential::withoutGlobalScopes()->where('user_id', $user->id)->count()
        );
        $this->assertSame('2222', $this->service()->for($user)?->last_four);
    }

    public function test_forget_removes_the_key(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $this->service()->store($user, 'sk-or-v1-removeme-9999');
        $this->service()->forget($user);

        $this->assertNull($this->service()->for($user));
    }

    public function test_one_owner_cannot_read_another_owners_key(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();

        ProviderCredential::factory()->create([
            'user_id' => $other->id,
            'provider' => Provider::OpenRouter,
        ]);

        $this->actingAs($owner);

        $this->assertNull($this->service()->for($owner));
        $this->assertSame(0, ProviderCredential::count());
    }

    public function test_empty_key_is_rejected_at_the_request_layer(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->from(route('provider.edit'))
            ->put(route('provider.update'), ['api_key' => ''])
            ->assertSessionHasErrors('api_key')
            ->assertRedirect(route('provider.edit'));

        $this->assertNull($this->service()->for($user->fresh()));
    }
}
