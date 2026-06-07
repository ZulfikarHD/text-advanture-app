<?php

namespace App\Exceptions\Sessions;

use App\Models\Story;
use App\Services\SessionService;
use App\Services\StoryOverviewService;
use RuntimeException;

/**
 * Thrown when a session fork is attempted against a story that is not play-ready
 * (S-2.1.1, ADR 0012/0016).
 *
 * A fork must never produce a save that cannot run, so {@see SessionService}
 * fails closed with this error when the play-readiness gate
 * ({@see StoryOverviewService::readiness()}) is not satisfied -
 * the same gate the Overview surface renders. The controller maps it to an
 * error toast rather than a 500.
 */
class StoryNotPlayableException extends RuntimeException
{
    /**
     * Build the exception for a story that failed the play-readiness gate.
     *
     * @param  Story  $story  The story whose fork was rejected.
     */
    public static function for(Story $story): self
    {
        return new self("Story #{$story->getKey()} is not play-ready; a session cannot be forked until every play-readiness requirement is met.");
    }
}
