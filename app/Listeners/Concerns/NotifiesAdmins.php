<?php

namespace App\Listeners\Concerns;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

trait NotifiesAdmins
{
    /**
     * The recipient set for in-app notifications — at minimum, all Admins.
     *
     * @return Collection<int, User>
     */
    protected function adminRecipients(): Collection
    {
        return User::query()->where('role', UserRole::Admin)->get();
    }
}
