<?php

namespace Tests\Feature\Authoring;

use App\Models\Story;
use App\Models\User;
use App\Policies\StoryPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Auth\OwnershipIsolationTest;
use Tests\TestCase;

/**
 * Ownership tests for the first owner-scoped product model, stories (S-4.1.1).
 *
 * Mirrors {@see OwnershipIsolationTest} against the real
 * model: the owner global scope hides foreign stories, a direct reference to a
 * foreign story resolves to "not found", the StoryPolicy denies cross-owner
 * mutation, and creation stamps the authenticated owner.
 */
class StoryOwnershipTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_only_sees_their_own_stories(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();

        Story::factory()->create(['user_id' => $owner->id]);
        Story::factory()->create(['user_id' => $other->id]);

        $this->actingAs($owner);

        $this->assertSame(1, Story::count());
    }

    public function test_cross_owner_story_is_not_found_via_direct_reference(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();

        $foreign = Story::factory()->create(['user_id' => $other->id]);

        $this->actingAs($owner);

        $this->assertNull(Story::find($foreign->id));
    }

    public function test_policy_denies_cross_owner_update_and_delete(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();

        $foreign = Story::factory()->create(['user_id' => $other->id]);

        $policy = new StoryPolicy;

        $this->assertFalse($policy->update($owner, $foreign));
        $this->assertFalse($policy->delete($owner, $foreign));
    }

    public function test_policy_allows_owner_to_update_their_story(): void
    {
        $owner = User::factory()->create();

        $mine = Story::factory()->create(['user_id' => $owner->id]);

        $this->assertTrue((new StoryPolicy)->update($owner, $mine));
    }

    public function test_creating_a_story_stamps_the_authenticated_owner(): void
    {
        $owner = User::factory()->create();

        $this->actingAs($owner);

        // Leave user_id empty so BelongsToOwner stamps the current user.
        $story = Story::factory()->create(['user_id' => null]);

        $this->assertSame($owner->id, $story->user_id);
    }
}
