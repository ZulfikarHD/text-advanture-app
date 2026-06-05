<?php

namespace App\Policies;

use App\Models\ReviewItem;

/**
 * Authorization policy for {@see ReviewItem}.
 *
 * Review items are owner-scoped via their direct `user_id`; authorization is by
 * ownership alone via the base {@see OwnerPolicy} - no role/admin hierarchy.
 * Resolved automatically by Laravel's policy auto-discovery
 * (`App\Models\ReviewItem` -> `App\Policies\ReviewItemPolicy`).
 */
class ReviewItemPolicy extends OwnerPolicy {}
