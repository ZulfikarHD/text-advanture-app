<?php

namespace App\Policies;

use App\Models\Scopes\OwnerScope;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * Base authorization policy for owner-scoped resources.
 *
 * Concrete policies (e.g. StoryPolicy, SavePolicy) extend this so every owned
 * model authorizes view/update/delete by ownership alone — the engine has no
 * role/admin hierarchy, "multi-user" means account isolation only. Combined
 * with {@see OwnerScope}, a foreign row is invisible (404
 * on route-model binding) and, if reached directly, forbidden (403).
 */
abstract class OwnerPolicy
{
    /**
     * Determine whether the user can view any of their own records.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Model $model): bool
    {
        return $this->owns($user, $model);
    }

    /**
     * Determine whether the user can create a new record.
     */
    public function create(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Model $model): bool
    {
        return $this->owns($user, $model);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Model $model): bool
    {
        return $this->owns($user, $model);
    }

    /**
     * Determine whether the given user owns the model.
     */
    protected function owns(User $user, Model $model): bool
    {
        return $user->getKey() === $model->getAttribute('user_id');
    }
}
