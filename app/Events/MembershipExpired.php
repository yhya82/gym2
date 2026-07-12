<?php

namespace App\Events;

use App\Models\Member;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MembershipExpired
{
    use Dispatchable, SerializesModels;

    // Plain, non-broadcasting event: a dedicated broadcast listener (Phase 8)
    // reacts to this instead of the event itself implementing ShouldBroadcast.
    public function __construct(
        public readonly Member $member,
    ) {}

    /**
     * Single source of truth for this event's notification text, so the
     * DB-write listener (Phase 7) and the broadcast listener (Phase 8) can
     * never drift apart on wording despite being fully independent jobs.
     */
    public function message(): string
    {
        return "{$this->member->full_name} membership expired.";
    }
}
