<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\Plan;
use App\Models\User;

class PlanPolicy
{
    /**
     * Governs the standalone Plans management page/CRUD only. Selecting a
     * plan from a dropdown while creating/renewing a member is authorized
     * through MemberPolicy::create()/renew(), not through here — the
     * Receptionist needs read access to plan name/price/duration for that
     * workflow even though the "Plans" page itself is hidden from their
     * sidebar (§6) and plan management is Admin-only (§3.2 Restricted
     * Actions: "Manage plans", "Change prices").
     */
    public function viewAny(User $user): bool
    {
        return $user->role === UserRole::Admin;
    }

    public function view(User $user, Plan $plan): bool
    {
        return $user->role === UserRole::Admin;
    }

    public function create(User $user): bool
    {
        return $user->role === UserRole::Admin;
    }

    public function update(User $user, Plan $plan): bool
    {
        return $user->role === UserRole::Admin;
    }

    public function delete(User $user, Plan $plan): bool
    {
        return $user->role === UserRole::Admin;
    }

    public function restore(User $user, Plan $plan): bool
    {
        return $user->role === UserRole::Admin;
    }

    // No forceDelete: business data is never permanently removed (§11).
}
