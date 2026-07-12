<?php

namespace App\Events;

use App\Models\Member;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MembershipExpired
{
    use Dispatchable, SerializesModels;

    // Plain, non-broadcasting event for now — listeners (notification
    // fan-out) are built in Phase 7, and whether/how this also broadcasts
    // over WebSockets is decided in Phase 8.
    public function __construct(
        public readonly Member $member,
    ) {}
}
