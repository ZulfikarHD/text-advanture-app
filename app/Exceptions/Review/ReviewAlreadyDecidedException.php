<?php

namespace App\Exceptions\Review;

use App\Models\ReviewItem;
use RuntimeException;

/**
 * Thrown when a review item that is no longer pending is acted on (ADR 0003).
 *
 * A proposal has exactly one terminal decision (accepted | edited | rejected);
 * deciding an already-decided item would silently overwrite the recorded
 * who/when, so the gate fails closed instead. Typically surfaced to the author
 * as a "already reviewed" message rather than a hard error.
 */
class ReviewAlreadyDecidedException extends RuntimeException
{
    /**
     * Build the exception for an item that already has a terminal decision.
     */
    public static function for(ReviewItem $item): self
    {
        return new self("Review item #{$item->getKey()} is already [{$item->status->value}] and cannot be decided again.");
    }
}
