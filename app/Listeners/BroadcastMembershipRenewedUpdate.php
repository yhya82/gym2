<?php

namespace App\Listeners;

use App\Enums\NotificationType;
use App\Events\DashboardStatsUpdated;
use App\Events\MembershipRenewed;
use App\Events\UserNotified;
use App\Listeners\Concerns\NotifiesAdmins;
use App\Models\Member;
use Illuminate\Contracts\Queue\ShouldQueue;

class BroadcastMembershipRenewedUpdate implements ShouldQueue
{
    use NotifiesAdmins;

    public function handle(MembershipRenewed $event): void
    {
        DashboardStatsUpdated::dispatch([
            'active_members' => Member::active()->count(),
            'expired_members' => Member::expired()->count(),
        ]);

        foreach ($this->adminRecipients() as $admin) {
            UserNotified::dispatch($admin->id, NotificationType::MembershipRenewed, $event->message());
        }
    }
}
