<?php

namespace App\Listeners;

use App\Enums\NotificationType;
use App\Events\MemberRegistered;
use App\Listeners\Concerns\NotifiesAdmins;
use App\Models\Notification;
use Illuminate\Contracts\Queue\ShouldQueue;

class SendMemberRegisteredNotification implements ShouldQueue
{
    use NotifiesAdmins;

    /**
     * Handle the event.
     */
    public function handle(MemberRegistered $event): void
    {
        foreach ($this->adminRecipients() as $admin) {
            Notification::create([
                'user_id' => $admin->id,
                'type' => NotificationType::MemberCreated,
                'message' => $event->message(),
                'read_status' => false,
            ]);
        }
    }
}
