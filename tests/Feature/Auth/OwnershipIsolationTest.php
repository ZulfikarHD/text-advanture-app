<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Fixtures\OwnedFixture;
use Tests\Fixtures\OwnedFixturePolicy;
use Tests\TestCase;

/**
 * Negative tests for the account-isolation foundation (S-2.2.2).
 *
 * Proves that the owner global scope hides another user's rows, that a direct
 * reference to a foreign row resolves to "not found", and that the base owner
 * policy denies cross-owner mutation — the invariants every owned product
 * model (stories, saves, API keys) will inherit.
 */
class OwnershipIsolationTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_only_sees_their_own_records(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();

        OwnedFixture::withoutGlobalScopes()->create(['user_id' => $owner->id, 'title' => 'Mine']);
        OwnedFixture::withoutGlobalScopes()->create(['user_id' => $other->id, 'title' => 'Theirs']);

        $this->actingAs($owner);

        $this->assertSame(1, OwnedFixture::count());
        $this->assertSame('Mine', OwnedFixture::sole()->title);
    }

    public function test_cross_owner_record_is_not_found_via_direct_reference(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();

        $foreign = OwnedFixture::withoutGlobalScopes()->create([
            'user_id' => $other->id,
            'title' => 'Theirs',
        ]);

        $this->actingAs($owner);

        $this->assertNull(OwnedFixture::find($foreign->id));
    }

    public function test_policy_denies_cross_owner_update_and_delete(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();

        $foreign = OwnedFixture::withoutGlobalScopes()->create([
            'user_id' => $other->id,
            'title' => 'Theirs',
        ]);

        $policy = new OwnedFixturePolicy;

        $this->assertFalse($policy->update($owner, $foreign));
        $this->assertFalse($policy->delete($owner, $foreign));
    }

    public function test_policy_allows_owner_to_update_their_record(): void
    {
        $owner = User::factory()->create();

        $mine = OwnedFixture::withoutGlobalScopes()->create([
            'user_id' => $owner->id,
            'title' => 'Mine',
        ]);

        $policy = new OwnedFixturePolicy;

        $this->assertTrue($policy->update($owner, $mine));
    }

    public function test_creating_stamps_the_authenticated_owner(): void
    {
        $owner = User::factory()->create();

        $this->actingAs($owner);

        $fixture = OwnedFixture::create(['title' => 'Mine']);

        $this->assertSame($owner->id, $fixture->user_id);
    }
}
