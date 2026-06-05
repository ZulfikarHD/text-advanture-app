<?php

namespace App\Models\Scopes;

use App\Models\Concerns\BelongsToOwner;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;
use Illuminate\Support\Facades\Auth;

/**
 * Constrains every query for an owned model to the authenticated user.
 *
 * Applied automatically by {@see BelongsToOwner}. The
 * scope is intentionally a no-op when no user is authenticated (console
 * commands, seeders, queued jobs); web traffic for owned resources always
 * sits behind the `auth` middleware, so this default-on constraint is the
 * fail-closed isolation boundary for HTTP requests — a user can never read
 * another user's rows, and a foreign key reference simply resolves to "not
 * found" rather than leaking the row's existence.
 */
class OwnerScope implements Scope
{
    /**
     * Apply the owner constraint to a given Eloquent query builder.
     */
    public function apply(Builder $builder, Model $model): void
    {
        if (Auth::check()) {
            $builder->where($model->getTable().'.user_id', Auth::id());
        }
    }
}
