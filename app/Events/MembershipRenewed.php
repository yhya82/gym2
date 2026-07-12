<?php

namespace App\Events;

use App\Models\Member;
use App\Models\Subscription;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MembershipRenewed
{
    use Dispatchable, SerializesModels;

    // Plain, non-broadcasting event — see App\Events\MembershipExpired for why.
    public function __construct(
        public readonly Member $member,
        public readonly Subscription $subscription,
    ) {}

    /**
     * Single source of truth for this event's notification text, so the
     * DB-write listener (Phase 7) and the broadcast listener (Phase 8) can
     * never drift apart on wording despite being fully independent jobs.
     */
    public function message(): string
    {
        return "{$this->member->full_name} renewed {$this->subscription->plan->plan_name} membership.";
    }
}
