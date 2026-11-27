<?php

namespace App\Livewire;

use App\Enums\MembershipStatus;
use App\Models\Expense;
use App\Models\Member;
use App\Models\Payment;
use App\Models\Subscription;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Livewire\Attributes\Computed;
use Livewire\Component;

class AdminDashboard extends Component
{
    public const REVENUE_PERIODS = ['daily', 'monthly', 'yearly', 'total'];

    /**
     * The chart's range menu (top-right of the chart), per Revenue card
     * period: option value => label. The value is how many points the chart
     * shows — days, months or years, matching the period.
     */
    public const CHART_RANGES = [
        'daily' => ['7' => 'Last 7 days', '14' => 'Last 14 days', '30' => 'Last 30 days'],
        'monthly' => ['3' => 'Last 3 months', '6' => 'Last 6 months', '12' => 'Last 12 months'],
        'yearly' => ['3' => 'Last 3 years', '5' => 'Last 5 years'],
        'total' => ['6' => 'Last 6 months', '12' => 'Last 12 months', '24' => 'Last 24 months'],
    ];

    private const CHART_DEFAULT_RANGE = ['daily' => '14', 'monthly' => '6', 'yearly' => '5', 'total' => '12'];

    public array $stats = [];

    /** @var array{labels: list<string>, values: list<float>} */
    public array $revenueSeries = ['labels' => [], 'values' => []];

    /** The chart's range — see CHART_RANGES. */
    public string $revenueRange = '6';

    /** The Revenue card's Daily / Monthly / Yearly / Total filter; the chart follows it. */
    public string $revenuePeriod = 'monthly';

    public function setRevenuePeriod(string $period): void
    {
        if (! in_array($period, self::REVENUE_PERIODS, true)) {
            return;
        }

        $this->revenuePeriod = $period;

        // Keep the range if the new period offers it (e.g. "12" months
        // monthly → "12" months cumulative); otherwise that period's default.
        if (! isset(self::CHART_RANGES[$period][$this->revenueRange])) {
            $this->revenueRange = self::CHART_DEFAULT_RANGE[$period];
        }

        $this->refreshRevenueSeries();
    }

    /**
     * The Revenue card for the selected period: the figure, and how it
     * compares with the matching earlier period.
     *
     * Comparisons are like-for-like so the pill means something on any day:
     * month-to-date is compared with the same days of last month (not all of
     * last month, which would read as "down" until the month ends), and
     * year-to-date with the same span of last year. "Total" can only ever
     * grow, so its pill is neutral and shows how much this month added.
     *
     * Read fresh on every render rather than cached: the broadcast listeners
     * (onRevenueUpdated) trigger a re-render whenever a payment is recorded,
     * which is what keeps the card live. Each period is at most two
     * indexed sums over payments.payment_date.
     *
     * @return array{label: string, value: float, pct: ?float, note: string, tone: string, direction: string}
     */
    #[Computed]
    public function revenueCard(): array
    {
        $today = now()->startOfDay();

        $card = match ($this->revenuePeriod) {
            'daily' => $this->compare(
                __('Today · :date', ['date' => $today->format('D j M')]),
                $this->revenueBetween($today, $today),
                $this->revenueBetween($today->copy()->subDay(), $today->copy()->subDay()),
                __('vs yesterday'),
            ),
            'yearly' => $this->compare(
                __('This year · 1 Jan – :date', ['date' => $today->format('j M')]),
                $this->revenueBetween($today->copy()->startOfYear(), $today),
                $this->revenueBetween(
                    $today->copy()->subYearNoOverflow()->startOfYear(),
                    $today->copy()->subYearNoOverflow(),
                ),
                __('vs same period last year'),
            ),
            'total' => $this->totalRevenueCard($today),
            default => $this->monthlyRevenueCard($today),
        };

        // Payments and average payment for the very same window as the
        // headline figure, so the three numbers always agree.
        [$from, $to] = match ($this->revenuePeriod) {
            'daily' => [$today, $today],
            'yearly' => [$today->copy()->startOfYear(), $today],
            'total' => [null, null],
            default => [$today->copy()->startOfMonth(), $today],
        };

        $payments = Payment::query()
            ->when($from, fn ($q) => $q->whereBetween('payment_date', [$from->toDateString(), $to->toDateString()]))
            ->count();

        return [
            ...$card,
            'payments' => $payments,
            'average' => $payments > 0 ? round($card['value'] / $payments, 2) : 0.0,
        ];
    }

    /**
     * Money still owed right now: the unpaid balance on every active
     * subscription of a current (not archived) member. Not tied to the
     * card's period filter — it's a "today" figure.
     *
     * @return array{amount: float, members: int}
     */
    #[Computed]
    public function outstanding(): array
    {
        $owing = Subscription::query()
            ->where('status', MembershipStatus::Active)
            ->where('balance', '>', 0)
            ->whereHas('member'); // excludes archived (soft-deleted) members

        return [
            'amount' => (float) (clone $owing)->sum('balance'),
            'members' => (clone $owing)->distinct()->count('member_id'),
        ];
    }

    private function monthlyRevenueCard(Carbon $today): array
    {
        $lastMonthStart = $today->copy()->subMonthNoOverflow()->startOfMonth();
        // Same day of the month last month, clamped (31 Mar → 28/29 Feb).
        $lastMonthEnd = $lastMonthStart->copy()->day(min($today->day, $lastMonthStart->daysInMonth));

        $label = $today->day === 1
            ? __('This month · :date', ['date' => $today->format('j M')])
            : __('This month · 1–:date', ['date' => $today->format('j M')]);

        return $this->compare(
            $label,
            $this->revenueBetween($today->copy()->startOfMonth(), $today),
            $this->revenueBetween($lastMonthStart, $lastMonthEnd),
            __('vs same days last month'),
        );
    }

    private function totalRevenueCard(Carbon $today): array
    {
        $total = (float) Payment::sum('amount');
        $thisMonth = $this->revenueBetween($today->copy()->startOfMonth(), $today);
        $before = $total - $thisMonth;

        return [
            'label' => __('All time'),
            'value' => $total,
            'pct' => $before > 0 ? round($thisMonth / $before * 100, 1) : null,
            'note' => __('added this month'),
            'tone' => 'neutral',
            'direction' => $thisMonth > 0 ? 'up' : 'flat',
        ];
    }

    /**
     * Revenue is good news going up: green when up, red when down, grey when
     * unchanged or when there's nothing earlier to compare against.
     */
    private function compare(string $label, float $current, float $previous, string $note): array
    {
        $pct = match (true) {
            $previous > 0 => round(($current - $previous) / $previous * 100, 1),
            $current == 0.0 => 0.0,
            default => null, // something now, nothing before: no meaningful %
        };

        $direction = $pct === null ? 'up' : ($pct > 0 ? 'up' : ($pct < 0 ? 'down' : 'flat'));

        return [
            'label' => $label,
            'value' => $current,
            'pct' => $pct,
            'note' => $pct === null ? __('no earlier revenue to compare') : $note,
            'tone' => match ($direction) {
                'up' => $pct === null ? 'neutral' : 'good', 'down' => 'bad', default => 'neutral'
            },
            'direction' => $direction,
        ];
    }

    private function revenueBetween(Carbon $from, Carbon $to): float
    {
        return (float) Payment::query()
            ->whereBetween('payment_date', [$from->toDateString(), $to->toDateString()])
            ->sum('amount');
    }

    public function mount(): void
    {
        $this->refreshMemberStats();
        $this->refreshRevenueStats();
    }

    public function updatedRevenueRange(): void
    {
        // The range comes from the browser: anything not offered for the
        // current period falls back to its default.
        if (! isset(self::CHART_RANGES[$this->revenuePeriod][$this->revenueRange])) {
            $this->revenueRange = self::CHART_DEFAULT_RANGE[$this->revenuePeriod];
        }

        $this->refreshRevenueSeries();
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

        // A new payment changes the chart too, not just the card.
        $this->refreshRevenueSeries();
    }

    private function refreshMemberStats(): void
    {
        $this->stats = [
            ...$this->stats,
            'total_members' => Cache::remember(
                'dashboard.total_members',
                now()->addMinutes(10),
                fn () => Member::count()
            ),
            'active_members' => Cache::remember(
                'dashboard.active_members',
                now()->addMinutes(10),
                fn () => Member::active()->count()
            ),
            'expired_members' => Cache::remember(
                'dashboard.expired_members',
                now()->addMinutes(10),
                fn () => Member::expired()->count()
            ),
        ];
    }

    private function refreshRevenueStats(): void
    {
        $this->stats = [
            ...$this->stats,
            'total_revenue' => Cache::remember(
                'dashboard.total_revenue',
                now()->addMinutes(10),
                fn () => (string) Payment::sum('amount')
            ),

            'monthly_revenue' => Cache::remember(
                'dashboard.monthly_revenue',
                now()->addMinutes(10),
                fn () => (string) Payment::whereMonth('payment_date', now()->month)
                    ->whereYear('payment_date', now()->year)
                    ->sum('amount')
            ),

            'daily_revenue' => Cache::remember(
                'dashboard.daily_revenue',
                now()->addMinutes(10),
                fn () => (string) Payment::whereDate(
                    'payment_date',
                    now()->toDateString()
                )->sum('amount')
            ),

            'total_expenses' => Cache::remember(
                'dashboard.total_expenses',
                now()->addMinutes(10),
                fn () => (string) Expense::sum('amount')
            ),

            'monthly_expenses' => Cache::remember(
                'dashboard.monthly_expenses',
                now()->addMinutes(10),
                fn () => (string) Expense::whereMonth('expense_date', now()->month)
                    ->whereYear('expense_date', now()->year)
                    ->sum('amount')
            ),
        ];

        $this->refreshRevenueSeries();
    }

    /**
     * The chart's title, following the Revenue card's filter.
     */
    public function chartTitle(): string
    {
        return match ($this->revenuePeriod) {
            'daily' => __('Daily Revenue'),
            'yearly' => __('Yearly Revenue'),
            'total' => __('Cumulative Revenue'),
            default => __('Monthly Revenue'),
        };
    }

    /**
     * The revenue chart's data for the card's period and the chart's range:
     * one point per day, month or year, oldest first, the current one last.
     * "Total" is a running total — everything before the window is the
     * starting point, then each month adds its revenue — so the line shows
     * how the all-time figure grew.
     */
    private function refreshRevenueSeries(): void
    {
        $range = isset(self::CHART_RANGES[$this->revenuePeriod][$this->revenueRange])
            ? $this->revenueRange
            : self::CHART_DEFAULT_RANGE[$this->revenuePeriod];
        $points = (int) $range;

        $this->revenueSeries = match ($this->revenuePeriod) {
            'daily' => $this->dailySeries($points),
            'yearly' => $this->yearlySeries($points),
            'total' => $this->cumulativeSeries($points),
            default => $this->monthlySeries($points),
        };
    }

    private function dailySeries(int $days): array
    {
        $start = now()->startOfDay()->subDays($days - 1);

        $rows = Payment::query()
            ->selectRaw('payment_date as d, SUM(amount) as total')
            ->where('payment_date', '>=', $start->toDateString())
            ->groupBy('payment_date')
            ->pluck('total', 'd')
            ->mapWithKeys(fn ($total, $d) => [Carbon::parse($d)->toDateString() => (float) $total]);

        $series = ['labels' => [], 'values' => []];
        for ($day = $start->copy(); $day->lte(now()); $day->addDay()) {
            $series['labels'][] = $day->format('j M');
            $series['values'][] = $rows[$day->toDateString()] ?? 0.0;
        }

        return $series;
    }

    private function monthlySeries(int $months, bool $withYear = false): array
    {
        $rows = $this->monthlyTotalsSince(now()->subMonthsNoOverflow($months - 1)->startOfMonth());

        $series = ['labels' => [], 'values' => []];
        for ($i = $months - 1; $i >= 0; $i--) {
            // subMonthsNoOverflow (not subMonths): on the 29th–31st, plain
            // subMonths can roll into the *next* month when the target month
            // is shorter (Sep 30 minus 7 months overflows into March), which
            // would drop a real month and duplicate its neighbour.
            $month = now()->subMonthsNoOverflow($i);
            // Up to 12 points never repeat a month name; beyond that the year
            // is needed to tell e.g. this Nov from last Nov.
            $series['labels'][] = $month->format($withYear || $months > 12 ? 'M y' : 'M');
            $series['values'][] = (float) ($rows[$month->format('Y-m')] ?? 0);
        }

        return $series;
    }

    private function yearlySeries(int $years): array
    {
        $firstYear = now()->year - $years + 1;

        $rows = Payment::query()
            ->selectRaw('YEAR(payment_date) as y, SUM(amount) as total')
            ->where('payment_date', '>=', "{$firstYear}-01-01")
            ->groupBy('y')
            ->pluck('total', 'y');

        $series = ['labels' => [], 'values' => []];
        for ($year = $firstYear; $year <= now()->year; $year++) {
            $series['labels'][] = (string) $year;
            $series['values'][] = (float) ($rows[$year] ?? 0);
        }

        return $series;
    }

    private function cumulativeSeries(int $months): array
    {
        $windowStart = now()->subMonthsNoOverflow($months - 1)->startOfMonth();
        $running = (float) Payment::where('payment_date', '<', $windowStart->toDateString())->sum('amount');

        $monthly = $this->monthlySeries($months, withYear: true);

        foreach ($monthly['values'] as $i => $value) {
            $running += $value;
            $monthly['values'][$i] = round($running, 2);
        }

        return $monthly;
    }

    /**
     * @return Collection<string, string> "Y-m" => total
     */
    private function monthlyTotalsSince(Carbon $start)
    {
        return Payment::query()
            ->selectRaw("DATE_FORMAT(payment_date, '%Y-%m') as ym, SUM(amount) as total")
            ->where('payment_date', '>=', $start->toDateString())
            ->groupBy('ym')
            ->pluck('total', 'ym');
    }

    public function render(): View
    {
        return view('livewire.admin-dashboard');
    }
}
