<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class DashboardRevenueUpdated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * On its own channel, separate from DashboardStatsUpdated, so revenue
     * figures are never even sent over the wire to a Receptionist's browser —
     * matching the same server-side exclusion already enforced by
     * PaymentPolicy::viewAny() (§3.2 — "Access financial reports" is
     * restricted), not just hidden in the UI.
     *
     * @param  array<string, string>  $revenue  e.g. ['total_revenue' => '1250.00', ...]
     */
    public function __construct(
        public readonly array $revenue,
    ) {}

    /**
     * @return array<int, Channel>
     */
    public function broadcastOn(): array
    {
        return [new PrivateChannel('dashboard.revenue')];
    }

    public function broadcastAs(): string
    {
        return 'revenue.updated';
    }

    public function broadcastWith(): array
    {
        return $this->revenue;
    }
}
