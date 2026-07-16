<?php

namespace App\Livewire;

use App\Models\Member;
use App\Models\Payment;
use Illuminate\Contracts\View\View;
use Livewire\Component;

class AdminDashboard extends Component
{
    /**
     * Months of history the revenue chart shows, keyed to the option values
     * in the view's <select> — a preset list rather than a free date range,
     * since the chart's own granularity is monthly.
     */
    private const REVENUE_RANGES = [
        '3' => 2,
        '6' => 5,
        '12' => 11,
    ];

    public array $stats = [];

    public array $monthlyRevenue = [];

    public string $revenueRange = '6';

    public function mount(): void
    {
        $this->refreshMemberStats();
        $this->refreshRevenueStats();
    }

    public function updatedRevenueRange(): void
    {
        $this->refreshMonthlyRevenueSeries();
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
     * Revenue by month, for the Monthly Revenue Chart (§9.2) — the window
     * defaults to 6 months back but is scoped by $revenueRange.
     */
    private function refreshMonthlyRevenueSeries(): void
    {
        $monthsBack = self::REVENUE_RANGES[$this->revenueRange] ?? self::REVENUE_RANGES['6'];

        $rows = Payment::query()
            ->selectRaw("DATE_FORMAT(payment_date, '%Y-%m') as ym, SUM(amount) as total")
            ->where('payment_date', '>=', now()->subMonths($monthsBack)->startOfMonth())
            ->groupBy('ym')
            ->orderBy('ym')
            ->pluck('total', 'ym');

        $series = [];
        for ($i = $monthsBack; $i >= 0; $i--) {
            $month = now()->subMonths($i);
            $key = $month->format('Y-m');
            // A rolling window of at most 12 months never repeats a calendar
            // month, so "M" alone (no year) is unambiguous at every range.
            $series[$month->format('M')] = (float) ($rows[$key] ?? 0);
        }

        $this->monthlyRevenue = $series;
    }

    public function render(): View
    {
        return view('livewire.admin-dashboard');
    }
}
