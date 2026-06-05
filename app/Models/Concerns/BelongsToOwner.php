<?php

namespace App\Models\Concerns;

use App\Models\Scopes\OwnerScope;
use App\Models\User;
use App\Policies\OwnerPolicy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Auth;

/**
 * Scopes an Eloquent model to its authenticated owner.
 *
 * Adds the {@see OwnerScope} global scope so every query is constrained to
 * the current user by default (fail-closed account isolation), and stamps
 * `user_id` with the authenticated user's id on create. Owned product models
 * (stories, saves, API keys, settings, ...) use this trait so one user can
 * never read or mutate another user's content. Pair it with a policy
 * extending {@see OwnerPolicy} for explicit authorization.
 *
 * @property int|null $user_id
 */
trait BelongsToOwner
{
    /**
     * Boot the trait: apply the owner global scope and stamp the owner on create.
     */
    public static function bootBelongsToOwner(): void
    {
        static::addGlobalScope(new OwnerScope);

        static::creating(function (Model $model): void {
            // Stamp ownership from the session so callers never have to pass it
            // and can never accidentally create a row owned by someone else.
            if (Auth::check() && empty($model->getAttribute('user_id'))) {
                $model->setAttribute('user_id', Auth::id());
            }
        });
    }

    /**
     * The user that owns this record.
     *
     * @return BelongsTo<User, $this>
     */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
