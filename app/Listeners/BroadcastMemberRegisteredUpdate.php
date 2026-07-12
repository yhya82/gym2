<?php

namespace App\Listeners;

use App\Enums\NotificationType;
use App\Events\DashboardStatsUpdated;
use App\Events\MemberRegistered;
use App\Events\UserNotified;
use App\Listeners\Concerns\NotifiesAdmins;
use App\Models\Member;
use Illuminate\Contracts\Queue\ShouldQueue;

class BroadcastMemberRegisteredUpdate implements ShouldQueue
{
    use NotifiesAdmins;

    /**
     * Deliberately a separate listener from SendMemberRegisteredNotification
     * (the Phase 7 DB-write listener) — if broadcasting fails here, the
     * already-persisted notification row is unaffected, and vice versa.
     */
    public function handle(MemberRegistered $event): void
    {
        DashboardStatsUpdated::dispatch([
            'total_members' => Member::count(),
            'active_members' => Member::active()->count(),
        ]);

        foreach ($this->adminRecipients() as $admin) {
            UserNotified::dispatch($admin->id, NotificationType::MemberCreated, $event->message());
        }
    }
}
