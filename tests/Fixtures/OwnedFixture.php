<?php

namespace Tests\Fixtures;

use App\Models\Concerns\BelongsToOwner;
use Illuminate\Database\Eloquent\Model;

/**
 * Minimal owned model used to validate the account-isolation foundation.
 *
 * Exercises {@see BelongsToOwner} (owner global scope + ownership stamping)
 * without committing a premature product model — the first real owned model
 * (stories) lands in Phase 2.
 */
class OwnedFixture extends Model
{
    use BelongsToOwner;

    protected $table = 'owned_fixtures';

    /** @var list<string> */
    protected $fillable = ['title', 'user_id'];
}
