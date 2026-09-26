<?php

namespace App\Listeners;

use App\Events\DashboardRevenueUpdated;
use App\Events\ExpenseRecorded;
use App\Models\Expense;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Cache;

class BroadcastExpenseRecordedUpdate implements ShouldQueue
{
    /**
     * Reuses the same Admin-only 'dashboard.revenue' channel/event as
     * revenue — expenses are the same class of financial data, and
     * AdminDashboard's onRevenueUpdated() already merges whatever keys the
     * payload carries into $stats, so no separate channel/event is needed.
     */
    public function handle(ExpenseRecorded $event): void
    {
        Cache::forget('dashboard.total_expenses');
        Cache::forget('dashboard.monthly_expenses');

        DashboardRevenueUpdated::dispatch([
            'total_expenses' => (string) Expense::sum('amount'),
            'monthly_expenses' => (string) Expense::whereMonth('expense_date', now()->month)
                ->whereYear('expense_date', now()->year)
                ->sum('amount'),
        ]);
    }
}
