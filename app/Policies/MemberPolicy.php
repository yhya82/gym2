<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\Member;
use App\Models\User;

class MemberPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Member $member): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Member $member): bool
    {
        return true;
    }

    public function renew(User $user, Member $member): bool
    {
        return true;
    }

    /**
     * Archiving a member (soft delete) is Admin-only — the Receptionist
     * cannot delete members (§3.2 Restricted Actions).
     */
    public function delete(User $user, Member $member): bool
    {
        return $user->role === UserRole::Admin;
    }

    public function restore(User $user, Member $member): bool
    {
        return $user->role === UserRole::Admin;
    }

    // No forceDelete: business data is never permanently removed (§11).

    /**
     * Viewing the "Archived" filter/tab is listed only under Admin's
     * permissions (§3.1); the Receptionist's allowed actions omit it.
     */
    public function viewArchived(User $user): bool
    {
        return $user->role === UserRole::Admin;
    }
}
