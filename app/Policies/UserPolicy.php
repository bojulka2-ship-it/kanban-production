<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Auth\Access\Response;

class UserPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->role->value === 'manager';
    }

    public function view(User $user, User $model): bool
    {
        return $user->role->value === 'manager';
    }

    public function create(User $user): bool
    {
        return $user->role->value === 'manager';
    }

    public function update(User $user, User $model): bool
    {
        return $user->role->value === 'manager';
    }

    public function delete(User $user, User $model): bool
    {
        return $user->role->value === 'manager';
    }

    public function manage(User $user): bool
    {
        return $user->role->value === 'manager';
    }
}
