<?php

namespace App\Listeners;

use App\Events\DashboardRevenueUpdated;
use App\Events\PaymentRecorded;
use App\Models\Payment;
use Illuminate\Contracts\Queue\ShouldQueue;

class BroadcastPaymentRecordedUpdate implements ShouldQueue
{
    /**
     * No UserNotified dispatch here at all — per §13.2, payments and revenue
     * updates never generate a notification, only the dashboard stat push.
     */
    public function handle(PaymentRecorded $event): void
    {
        $today = now()->toDateString();

        DashboardRevenueUpdated::dispatch([
            'total_revenue' => (string) Payment::sum('amount'),
            'monthly_revenue' => (string) Payment::whereMonth('payment_date', now()->month)
                ->whereYear('payment_date', now()->year)
                ->sum('amount'),
            'daily_revenue' => (string) Payment::whereDate('payment_date', $today)->sum('amount'),
        ]);
    }
}
