<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class DashboardStatsUpdated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * Member-count stats only — both Admin and Receptionist can see these
     * (§12.2), unlike revenue figures (see DashboardRevenueUpdated).
     *
     * @param  array<string, int>  $stats  e.g. ['total_members' => 12, 'active_members' => 9]
     */
    public function __construct(
        public readonly array $stats,
    ) {}

    /**
     * @return array<int, Channel>
     */
    public function broadcastOn(): array
    {
        return [new PrivateChannel('dashboard')];
    }

    public function broadcastAs(): string
    {
        return 'stats.updated';
    }

    public function broadcastWith(): array
    {
        return $this->stats;
    }
}
