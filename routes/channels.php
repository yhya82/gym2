<?php

use App\Enums\UserRole;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

// Member-count stats: both roles can see these (§12.2).
Broadcast::channel('dashboard', function ($user) {
    return in_array($user->role, [UserRole::Admin, UserRole::Receptionist], true);
});

// Revenue figures: Admin-only, kept on a separate channel so the numbers are
// never sent to a Receptionist's browser in the first place (§3.2 — "Access
// financial reports" is restricted; this is the real-time analogue of
// PaymentPolicy::viewAny()).
Broadcast::channel('dashboard.revenue', function ($user) {
    return $user->role === UserRole::Admin;
});
