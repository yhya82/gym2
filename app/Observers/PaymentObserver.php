<?php

namespace App\Observers;

use App\Models\Payment;
use App\Services\AuditLogger;

class PaymentObserver
{
    public function __construct(
        private readonly AuditLogger $audit,
    ) {}

    // Only created() — payment history is immutable (Rule 3), there is no
    // update or delete path for a Payment anywhere in the app.
    public function created(Payment $payment): void
    {
        // withTrashed: the name must still resolve if the member is archived
        // later, or was archived when this ran.
        $name = $payment->member()->withTrashed()->value('full_name') ?? "member #{$payment->member_id}";

        $this->audit->log(
            'create',
            'payments',
            "Recorded payment of {$payment->amount} for \"{$name}\"."
        );
    }
}
