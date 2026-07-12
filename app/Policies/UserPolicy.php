<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\User;

class UserPolicy
{
    /**
     * User management is Admin-only throughout — Receptionist's Restricted
     * Actions explicitly include "Manage users" (§3.2), and the Receptionist
     * sidebar has no access to it at all (§6).
     */
    public function viewAny(User $user): bool
    {
        return $user->role === UserRole::Admin;
    }

    public function view(User $user, User $model): bool
    {
        return $user->role === UserRole::Admin;
    }

    public function create(User $user): bool
    {
        return $user->role === UserRole::Admin;
    }

    public function update(User $user, User $model): bool
    {
        return $user->role === UserRole::Admin;
    }

    public function delete(User $user, User $model): bool
    {
        return $user->role === UserRole::Admin;
    }

    public function restore(User $user, User $model): bool
    {
        return $user->role === UserRole::Admin;
    }

    // No forceDelete: business data is never permanently removed (§11) —
    // "deleting" a user means deactivating (soft delete), not erasing.
}
