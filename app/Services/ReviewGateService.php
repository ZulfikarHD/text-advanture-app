<?php

namespace App\Services;

use App\Enums\ProducerType;
use App\Enums\ReviewStatus;
use App\Exceptions\Review\ReviewAlreadyDecidedException;
use App\Models\PlaySession;
use App\Models\ReviewItem;
use App\Models\User;
use Illuminate\Support\Facades\Auth;

/**
 * The shared review gate: propose -> review -> commit (S-6.2, ADR 0003/0012 §5).
 *
 * One queue every engine producer enqueues into. This is the Phase 1
 * foundation: it owns the queue and the decision state machine
 * (pending -> accepted | edited | rejected), recording the reviewer and
 * timestamp and capturing edits separately from the original payload. The
 * committed value is {@see ReviewItem::committedPayload()} (edits win); the
 * per-producer commit handlers that write into delta/beat/card tables land in
 * Phase 7 (PLACEHOLDER_TRACKING PH-27).
 *
 * Decisions fail closed: an item that already has a terminal decision throws
 * {@see ReviewAlreadyDecidedException} rather than overwriting the audit.
 */
class ReviewGateService
{
    /**
     * Enqueue a proposal from a producer.
     *
     * Authoring-time compiles (card/outline/bible) pass a null session - a
     * deliberate authoring-realm row in a save-realm table. Ownership is stamped
     * from the authenticated user by the owner scope, so the proposal is
     * isolated to its account even without a session.
     *
     * @param  array<string, mixed>  $payload  The proposed content.
     * @param  PlaySession|null  $session  The save context, or null for an authoring-time compile.
     * @param  int|null  $producerId  Optional id of the row that produced the proposal.
     */
    public function propose(
        ProducerType $producerType,
        array $payload,
        ?PlaySession $session = null,
        ?int $producerId = null,
    ): ReviewItem {
        return ReviewItem::create([
            'session_id' => $session?->getKey(),
            'producer_type' => $producerType,
            'producer_id' => $producerId,
            'payload' => $payload,
            'status' => ReviewStatus::Pending,
        ]);
    }

    /**
     * Accept a pending proposal as-is.
     *
     * @throws ReviewAlreadyDecidedException When the item is no longer pending.
     */
    public function accept(ReviewItem $item, User $reviewer): ReviewItem
    {
        return $this->decide($item, $reviewer, ReviewStatus::Accepted);
    }

    /**
     * Commit a pending proposal with author edits.
     *
     * The edited payload is stored separately from the original so the proposal
     * the engine produced remains auditable next to what the author committed.
     *
     * @param  array<string, mixed>  $payload  The edited content to commit.
     *
     * @throws ReviewAlreadyDecidedException When the item is no longer pending.
     */
    public function edit(ReviewItem $item, array $payload, User $reviewer): ReviewItem
    {
        return $this->decide($item, $reviewer, ReviewStatus::Edited, $payload);
    }

    /**
     * Reject a pending proposal; nothing is committed.
     *
     * @throws ReviewAlreadyDecidedException When the item is no longer pending.
     */
    public function reject(ReviewItem $item, User $reviewer): ReviewItem
    {
        return $this->decide($item, $reviewer, ReviewStatus::Rejected);
    }

    /**
     * Apply a terminal decision and stamp who/when, failing closed if already decided.
     *
     * @param  array<string, mixed>|null  $editedPayload  Edits to capture (edit decision only).
     *
     * @throws ReviewAlreadyDecidedException
     */
    private function decide(
        ReviewItem $item,
        User $reviewer,
        ReviewStatus $status,
        ?array $editedPayload = null,
    ): ReviewItem {
        if (! $item->status->isPending()) {
            throw ReviewAlreadyDecidedException::for($item);
        }

        $item->fill([
            'status' => $status,
            'edited_payload' => $editedPayload,
            'reviewed_at' => now(),
            'reviewed_by' => $this->reviewerLabel($reviewer),
        ])->save();

        return $item;
    }

    /**
     * The stored audit label for a reviewer (email is stable and identifying).
     */
    private function reviewerLabel(User $reviewer): string
    {
        return (string) ($reviewer->getAttribute('email') ?? Auth::id());
    }
}
