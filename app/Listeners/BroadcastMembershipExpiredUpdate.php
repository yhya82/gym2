<?php

namespace App\Listeners;

use App\Enums\NotificationType;
use App\Events\DashboardStatsUpdated;
use App\Events\MembershipExpired;
use App\Events\UserNotified;
use App\Listeners\Concerns\NotifiesAdmins;
use App\Models\Member;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Cache;

class BroadcastMembershipExpiredUpdate implements ShouldQueue
{
    use NotifiesAdmins;

    public function handle(MembershipExpired $event): void
    {
        Cache::forget('dashboard.total_members');
        Cache::forget('dashboard.active_members');
        Cache::forget('dashboard.expired_members');
        
        DashboardStatsUpdated::dispatch([
            'expired_members' => Member::expired()->count(),
        ]);

        foreach ($this->adminRecipients() as $admin) {
            UserNotified::dispatch($admin->id, NotificationType::MembershipExpired, $event->message());
        }
    }
}
