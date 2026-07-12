<?php

namespace App\Events;

use App\Enums\NotificationType;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class UserNotified implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * Carries plain scalar data rather than a Notification model on purpose:
     * this broadcast listener and the Phase 7 DB-write listener are
     * independent queued jobs reacting to the same domain event with no
     * ordering guarantee between them, so this can't depend on the other
     * having already persisted a row — each derives what it needs directly
     * from the event itself.
     */
    public function __construct(
        public readonly int $userId,
        public readonly NotificationType $type,
        public readonly string $message,
    ) {}

    /**
     * @return array<int, Channel>
     */
    public function broadcastOn(): array
    {
        return [new PrivateChannel("App.Models.User.{$this->userId}")];
    }

    public function broadcastAs(): string
    {
        return 'notification.created';
    }

    public function broadcastWith(): array
    {
        return [
            'type' => $this->type->value,
            'message' => $this->message,
        ];
    }
}
