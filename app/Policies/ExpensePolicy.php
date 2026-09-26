<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\Expense;
use App\Models\User;

class ExpensePolicy
{
    /**
     * The Expenses page is Admin-only in full — unlike Payments (where
     * recording a payment is shared but the report view is Admin-only),
     * there is no shared "log an expense" workflow, so every ability here
     * is restricted the same way.
     */
    public function viewAny(User $user): bool
    {
        return $user->role === UserRole::Admin;
    }

    public function view(User $user, Expense $expense): bool
    {
        return $user->role === UserRole::Admin;
    }

    public function create(User $user): bool
    {
        return $user->role === UserRole::Admin;
    }

    public function update(User $user, Expense $expense): bool
    {
        return $user->role === UserRole::Admin;
    }

    public function delete(User $user, Expense $expense): bool
    {
        return $user->role === UserRole::Admin;
    }

    public function restore(User $user, Expense $expense): bool
    {
        return $user->role === UserRole::Admin;
    }

    // No forceDelete: business data is never permanently removed.
}
