<?php

namespace App\Listeners;

use App\Enums\NotificationType;
use App\Events\MembershipRenewed;
use App\Listeners\Concerns\NotifiesAdmins;
use App\Models\Notification;
use Illuminate\Contracts\Queue\ShouldQueue;

class SendMembershipRenewedNotification implements ShouldQueue
{
    use NotifiesAdmins;

    /**
     * Handle the event.
     */
    public function handle(MembershipRenewed $event): void
    {
        foreach ($this->adminRecipients() as $admin) {
            Notification::create([
                'user_id' => $admin->id,
                'type' => NotificationType::MembershipRenewed,
                'message' => $event->message(),
                'read_status' => false,
            ]);
        }
    }
}
