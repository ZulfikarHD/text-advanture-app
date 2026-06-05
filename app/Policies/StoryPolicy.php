<?php

namespace App\Policies;

use App\Models\Story;

/**
 * Authorization policy for {@see Story}.
 *
 * Stories are the first owner-scoped product model. Authorization is by
 * ownership alone via the base {@see OwnerPolicy} - there is no role/admin
 * hierarchy. Resolved automatically by Laravel's policy auto-discovery
 * (`App\Models\Story` -> `App\Policies\StoryPolicy`).
 */
class StoryPolicy extends OwnerPolicy {}
