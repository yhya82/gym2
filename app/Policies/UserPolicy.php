<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Auth\Access\Response;

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

    /**
     * A user's profile page: Admins can open anyone's (Users page); everyone
     * else only their own (/profile).
     */
    public function view(User $user, User $model): bool
    {
        return $user->role === UserRole::Admin || $user->id === $model->id;
    }

    public function create(User $user): bool
    {
        return $user->role === UserRole::Admin;
    }

    public function update(User $user, User $model): bool
    {
        return $user->role === UserRole::Admin;
    }

    /**
     * Deactivating a user is Admin-only, plus two guards that have no other
     * enforcement point anywhere in the app: an Admin can't archive their
     * own account (self-lockout — restore is itself Admin-only, so there'd
     * be no one left to undo it), and the last remaining active Admin can't
     * be archived by anyone, self or otherwise, since that would leave the
     * system with zero users able to manage users, plans, or settings.
     */
    public function delete(User $user, User $model): Response
    {
        if ($user->role !== UserRole::Admin) {
            return Response::deny('Only an Admin can deactivate users.');
        }

        if ($user->id === $model->id) {
            return Response::deny('You cannot deactivate your own account.');
        }

        if ($model->role === UserRole::Admin && User::where('role', UserRole::Admin)->count() <= 1) {
            return Response::deny('At least one Admin account must remain active.');
        }

        return Response::allow();
    }

    public function restore(User $user, User $model): bool
    {
        return $user->role === UserRole::Admin;
    }

    /**
     * Changing someone's role, on top of update(). Same reasoning as
     * delete(): demoting the last Admin locks everyone out of user, plan and
     * settings management just as surely as deactivating them would. And no
     * Admin changes their own role, so an Admin can't accidentally demote
     * themselves out of the page they're using.
     */
    public function changeRole(User $user, User $model): Response
    {
        if ($user->role !== UserRole::Admin) {
            return Response::deny('Only an Admin can change roles.');
        }

        if ($user->id === $model->id) {
            return Response::deny('You cannot change your own role.');
        }

        if ($model->role === UserRole::Admin && User::where('role', UserRole::Admin)->count() <= 1) {
            return Response::deny('This is the only Admin account. Make another user an Admin first.');
        }

        return Response::allow();
    }

    /**
     * Everyone may change their own password (Profile). Only an Admin may
     * change anyone else's (Users page).
     */
    public function changePassword(User $user, User $model): Response
    {
        if ($user->id === $model->id || $user->role === UserRole::Admin) {
            return Response::allow();
        }

        return Response::deny("Only an Admin can change another user's password.");
    }

    // No forceDelete: business data is never permanently removed (§11) —
    // "deleting" a user means deactivating (soft delete), not erasing.
}
