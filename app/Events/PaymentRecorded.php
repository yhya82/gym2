<?php

namespace App\Events;

use App\Models\Payment;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PaymentRecorded
{
    use Dispatchable, SerializesModels;

    // Plain, non-broadcasting event — a dedicated broadcast listener (Phase 8)
    // reacts to this to push revenue stats live. Per §13.2, payments never
    // generate a notification, so — unlike MemberRegistered/
    // MembershipRenewed/MembershipExpired — nothing else listens to this.
    public function __construct(
        public readonly Payment $payment,
    ) {}
}
