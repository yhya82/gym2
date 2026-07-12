<?php

namespace App\Listeners;

use App\Enums\NotificationType;
use App\Events\DashboardStatsUpdated;
use App\Events\MembershipExpired;
use App\Events\UserNotified;
use App\Listeners\Concerns\NotifiesAdmins;
use App\Models\Member;
use Illuminate\Contracts\Queue\ShouldQueue;

class BroadcastMembershipExpiredUpdate implements ShouldQueue
{
    use NotifiesAdmins;

    public function handle(MembershipExpired $event): void
    {
        DashboardStatsUpdated::dispatch([
            'expired_members' => Member::expired()->count(),
        ]);

        foreach ($this->adminRecipients() as $admin) {
            UserNotified::dispatch($admin->id, NotificationType::MembershipExpired, $event->message());
        }
    }
}
