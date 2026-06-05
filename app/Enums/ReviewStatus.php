<?php

namespace App\Enums;

/**
 * Decision state of a shared review-gate item (ADR 0003).
 *
 * A proposal moves from `Pending` to one terminal state when the author acts:
 * accepted as-is, accepted with edits, or rejected. Mirrors the
 * `review_items.status` DB enum.
 */
enum ReviewStatus: string
{
    case Pending = 'pending';
    case Accepted = 'accepted';
    case Edited = 'edited';
    case Rejected = 'rejected';

    /**
     * A short human label for the status (review-gate UI).
     */
    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::Accepted => 'Accepted',
            self::Edited => 'Edited',
            self::Rejected => 'Rejected',
        };
    }

    /**
     * Whether the item still awaits a decision.
     */
    public function isPending(): bool
    {
        return $this === self::Pending;
    }
}
