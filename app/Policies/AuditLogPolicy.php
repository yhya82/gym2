<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\User;

class AuditLogPolicy
{
    /**
     * The audit trail is Admin-only — it's the record of every sensitive
     * action across the app (§19.1), which itself must stay outside the
     * Receptionist's Restricted-Actions boundary (§3.2).
     */
    public function viewAny(User $user): bool
    {
        return $user->role === UserRole::Admin;
    }
}
