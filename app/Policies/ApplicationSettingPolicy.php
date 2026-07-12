<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\ApplicationSetting;
use App\Models\User;

class ApplicationSettingPolicy
{
    // Settings is a singleton (§20 — "Only Admin can access settings"): it is
    // seeded once and never created/deleted via user action, so only view and
    // update are meaningful abilities here.

    public function view(User $user, ApplicationSetting $applicationSetting): bool
    {
        return $user->role === UserRole::Admin;
    }

    public function update(User $user, ApplicationSetting $applicationSetting): bool
    {
        return $user->role === UserRole::Admin;
    }
}
