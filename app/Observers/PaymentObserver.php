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
        $this->audit->log(
            'create',
            'payments',
            "Recorded payment of {$payment->amount} for member #{$payment->member_id}."
        );
    }
}
