<?php

namespace App\Listeners;

use App\Enums\NotificationType;
use App\Events\MembershipExpired;
use App\Listeners\Concerns\NotifiesAdmins;
use App\Models\Notification;
use Illuminate\Contracts\Queue\ShouldQueue;

class SendMembershipExpiredNotification implements ShouldQueue
{
    use NotifiesAdmins;

    /**
     * Handle the event.
     */
    public function handle(MembershipExpired $event): void
    {
        foreach ($this->adminRecipients() as $admin) {
            Notification::create([
                'user_id' => $admin->id,
                'type' => NotificationType::MembershipExpired,
                'message' => $event->message(),
                'read_status' => false,
            ]);
        }
    }
}
