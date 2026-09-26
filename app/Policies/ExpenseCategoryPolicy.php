<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\ExpenseCategory;
use App\Models\User;

class ExpenseCategoryPolicy
{
    /**
     * Expense categories are managed alongside expenses on the same
     * Admin-only page — Receptionist has no access to either (expenses are
     * financial data, same restriction as Payments' financial-reports view).
     */
    public function viewAny(User $user): bool
    {
        return $user->role === UserRole::Admin;
    }

    public function view(User $user, ExpenseCategory $category): bool
    {
        return $user->role === UserRole::Admin;
    }

    public function create(User $user): bool
    {
        return $user->role === UserRole::Admin;
    }

    public function update(User $user, ExpenseCategory $category): bool
    {
        return $user->role === UserRole::Admin;
    }

    public function delete(User $user, ExpenseCategory $category): bool
    {
        return $user->role === UserRole::Admin;
    }

    public function restore(User $user, ExpenseCategory $category): bool
    {
        return $user->role === UserRole::Admin;
    }

    // No forceDelete: business data is never permanently removed.
}
