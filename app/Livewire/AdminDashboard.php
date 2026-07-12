<?php

namespace App\Livewire;

use App\Models\Member;
use App\Models\Payment;
use Illuminate\Contracts\View\View;
use Livewire\Component;

class AdminDashboard extends Component
{
    public array $stats = [];

    public array $monthlyRevenue = [];

    public function mount(): void
    {
        $this->refreshMemberStats();
        $this->refreshRevenueStats();
    }

    /**
     * Both channels from Phase 8 — member-count stats and revenue are
     * broadcast separately (revenue is Admin-only), so this listens to both.
     */
    protected function getListeners(): array
    {
        return [
            'echo-private:dashboard,.stats.updated' => 'onStatsUpdated',
            'echo-private:dashboard.revenue,.revenue.updated' => 'onRevenueUpdated',
        ];
    }

    public function onStatsUpdated(array $event): void
    {
        $this->stats = [...$this->stats, ...$event];
    }

    public function onRevenueUpdated(array $event): void
    {
        $this->stats = [...$this->stats, ...$event];
        $this->refreshMonthlyRevenueSeries();
    }

    private function refreshMemberStats(): void
    {
        $this->stats = [
            ...$this->stats,
            'total_members' => Member::count(),
            'active_members' => Member::active()->count(),
            'expired_members' => Member::expired()->count(),
        ];
    }

    private function refreshRevenueStats(): void
    {
        $this->stats = [
            ...$this->stats,
            'total_revenue' => (string) Payment::sum('amount'),
            'monthly_revenue' => (string) Payment::whereMonth('payment_date', now()->month)
                ->whereYear('payment_date', now()->year)
                ->sum('amount'),
            'daily_revenue' => (string) Payment::whereDate('payment_date', now()->toDateString())->sum('amount'),
        ];
        $this->refreshMonthlyRevenueSeries();
    }

    /**
     * Last 6 months of revenue, for the Monthly Revenue Chart (§9.2).
     */
    private function refreshMonthlyRevenueSeries(): void
    {
        $rows = Payment::query()
            ->selectRaw("DATE_FORMAT(payment_date, '%Y-%m') as ym, SUM(amount) as total")
            ->where('payment_date', '>=', now()->subMonths(5)->startOfMonth())
            ->groupBy('ym')
            ->orderBy('ym')
            ->pluck('total', 'ym');

        $series = [];
        for ($i = 5; $i >= 0; $i--) {
            $month = now()->subMonths($i);
            $key = $month->format('Y-m');
            $series[$month->format('M')] = (float) ($rows[$key] ?? 0);
        }

        $this->monthlyRevenue = $series;
    }

    public function render(): View
    {
        return view('livewire.admin-dashboard');
    }
}
