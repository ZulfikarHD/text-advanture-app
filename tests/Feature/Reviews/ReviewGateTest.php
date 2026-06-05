<?php

namespace Tests\Feature\Reviews;

use App\Enums\ProducerType;
use App\Enums\ReviewStatus;
use App\Exceptions\Review\ReviewAlreadyDecidedException;
use App\Models\ReviewItem;
use App\Models\User;
use App\Services\ReviewGateService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Foundation tests for the shared review gate (S-6.2.1, S-6.2.2).
 *
 * Covers the decision state machine (pending -> accepted | edited | rejected)
 * with reviewer/timestamp recording, null-session authoring proposals, the
 * fail-closed guard against re-deciding, and the owner-scoped surface - a user
 * can neither see nor act on another owner's proposals (the Sprint-6 critical
 * negative test).
 */
class ReviewGateTest extends TestCase
{
    use RefreshDatabase;

    private function gate(): ReviewGateService
    {
        return new ReviewGateService;
    }

    public function test_proposing_enqueues_a_pending_item(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user);
        $item = $this->gate()->propose(ProducerType::Delta, ['axis' => 'affection']);

        $this->assertSame(ReviewStatus::Pending, $item->status);
        $this->assertSame($user->id, $item->user_id);
    }

    public function test_authoring_proposals_can_have_a_null_session(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user);
        $item = $this->gate()->propose(ProducerType::CardCompile, ['name' => 'Koakuma'], null);

        $this->assertNull($item->session_id);
        $this->assertSame($user->id, $item->user_id);
    }

    public function test_accepting_records_the_reviewer_and_timestamp(): void
    {
        $user = User::factory()->create();
        $item = ReviewItem::factory()->forOwner($user)->create();

        $accepted = $this->gate()->accept($item, $user);

        $this->assertSame(ReviewStatus::Accepted, $accepted->status);
        $this->assertSame($user->email, $accepted->reviewed_by);
        $this->assertNotNull($accepted->reviewed_at);
    }

    public function test_editing_captures_the_edit_separately_and_commits_it(): void
    {
        $user = User::factory()->create();
        $item = ReviewItem::factory()->forOwner($user)->create(['payload' => ['note' => 'original']]);

        $edited = $this->gate()->edit($item, ['note' => 'edited'], $user);

        $this->assertSame(ReviewStatus::Edited, $edited->status);
        $this->assertSame(['note' => 'edited'], $edited->edited_payload);
        $this->assertSame(['note' => 'edited'], $edited->committedPayload());
    }

    public function test_rejecting_commits_nothing(): void
    {
        $user = User::factory()->create();
        $item = ReviewItem::factory()->forOwner($user)->create();

        $rejected = $this->gate()->reject($item, $user);

        $this->assertSame(ReviewStatus::Rejected, $rejected->status);
        $this->assertNull($rejected->edited_payload);
    }

    public function test_an_already_decided_item_cannot_be_decided_again(): void
    {
        $user = User::factory()->create();
        $item = ReviewItem::factory()->forOwner($user)->create();

        $this->gate()->accept($item, $user);

        $this->expectException(ReviewAlreadyDecidedException::class);

        $this->gate()->reject($item, $user);
    }

    public function test_index_renders_with_a_deferred_owner_scoped_item_list(): void
    {
        $user = User::factory()->create();
        ReviewItem::factory()->forOwner($user)->count(2)->create();

        $this->actingAs($user)
            ->get(route('reviews.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('reviews/Index')
                ->missing('items')
                ->loadDeferredProps(fn (Assert $reload) => $reload
                    ->has('items.data', 2)
                    ->etc()
                )
            );
    }

    public function test_a_user_never_sees_another_owners_proposals(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();

        ReviewItem::factory()->forOwner($owner)->count(2)->create();
        ReviewItem::factory()->forOwner($other)->count(3)->create();

        $this->actingAs($owner)
            ->get(route('reviews.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->loadDeferredProps(fn (Assert $reload) => $reload
                    ->has('items.data', 2)
                    ->where('items.total', 2)
                    ->etc()
                )
            );
    }

    public function test_a_user_cannot_act_on_another_owners_proposal(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $item = ReviewItem::factory()->forOwner($other)->create();

        $this->actingAs($owner)
            ->post(route('reviews.accept', $item))
            ->assertNotFound();

        $this->assertDatabaseHas('review_items', [
            'id' => $item->id,
            'status' => ReviewStatus::Pending->value,
        ]);
    }

    public function test_owner_can_accept_their_own_proposal_via_the_endpoint(): void
    {
        $user = User::factory()->create();
        $item = ReviewItem::factory()->forOwner($user)->create();

        $this->actingAs($user)
            ->post(route('reviews.accept', $item))
            ->assertRedirect(route('reviews.index'));

        $this->assertSame(ReviewStatus::Accepted, $item->fresh()->status);
    }

    public function test_owner_can_commit_an_edit_via_the_endpoint(): void
    {
        $user = User::factory()->create();
        $item = ReviewItem::factory()->forOwner($user)->create();

        $this->actingAs($user)
            ->put(route('reviews.update', $item), ['payload' => ['note' => 'edited']])
            ->assertRedirect(route('reviews.index'));

        $this->assertSame(['note' => 'edited'], $item->fresh()->edited_payload);
    }

    public function test_guests_are_redirected_to_login(): void
    {
        $this->get(route('reviews.index'))->assertRedirect(route('login'));
    }
}
