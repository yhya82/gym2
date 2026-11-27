@php $currency = \App\Models\ApplicationSetting::current()->currency; @endphp

<div class="space-y-6">
    <div>
        <h1 class="text-xl font-semibold text-gray-900 dark:text-gray-100">{{ __('Dashboard') }}</h1>
        <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('Live overview of revenue and membership.') }}</p>
    </div>

    @php
        $revenue = $this->revenueCard;
        $periodLabels = ['daily' => __('Daily'), 'monthly' => __('Monthly'), 'yearly' => __('Yearly'), 'total' => __('Total')];
        $pillTone = match ($revenue['tone']) {
            'good' => 'bg-green-100 text-green-800 dark:bg-green-500/15 dark:text-green-300',
            'bad' => 'bg-red-100 text-red-800 dark:bg-red-500/15 dark:text-red-300',
            default => 'bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-200',
        };
        $pillText = $revenue['pct'] === null
            ? __('New')
            : ($revenue['pct'] > 0 ? '+' : ($revenue['pct'] < 0 ? '−' : '')).number_format(abs($revenue['pct']), 1).'%';
    @endphp

    {{-- Revenue (2/3) + Expenses (1/3) on large screens; stacked below that. --}}
    <div class="grid gap-5 lg:grid-cols-3">
        <section aria-labelledby="revenue-heading" class="lg:col-span-2 rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 shadow-sm">
            <div class="flex flex-wrap items-center justify-between gap-3 px-5 sm:px-6 py-4 border-b border-gray-100 dark:border-gray-700">
                <div class="flex items-center gap-3">
                    <span aria-hidden="true" class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-indigo-50 dark:bg-indigo-500/10 text-indigo-600 dark:text-indigo-400">
                        <x-icon name="revenue" class="h-5 w-5" />
                    </span>
                    <div>
                        <h2 id="revenue-heading" class="text-base font-semibold text-gray-900 dark:text-gray-100">{{ __('Revenue') }}</h2>
                        <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('Payments received') }}</p>
                    </div>
                </div>

                {{-- Full width on phones (four equal buttons), compact from sm up. --}}
                <div role="group" aria-label="{{ __('Revenue period') }}" class="grid w-full grid-cols-4 gap-0.5 rounded-lg bg-gray-100 dark:bg-gray-700/60 p-1 sm:inline-flex sm:w-auto">
                    @foreach ($periodLabels as $key => $label)
                        <button
                            type="button"
                            wire:click="setRevenuePeriod('{{ $key }}')"
                            aria-pressed="{{ $revenuePeriod === $key ? 'true' : 'false' }}"
                            @class([
                                'min-h-[36px] rounded-md px-3 text-sm transition-colors',
                                'bg-white dark:bg-gray-800 font-semibold text-indigo-700 dark:text-indigo-300 shadow-sm' => $revenuePeriod === $key,
                                'font-medium text-gray-600 dark:text-gray-300 hover:text-gray-900 dark:hover:text-white' => $revenuePeriod !== $key,
                            ])
                        >{{ $label }}</button>
                    @endforeach
                </div>
            </div>

            <div class="px-5 sm:px-6 py-6 space-y-3" wire:loading.class="opacity-60" wire:target="setRevenuePeriod">
                <p class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ $revenue['label'] }}</p>
                <p class="flex flex-wrap items-baseline gap-x-2">
                    <span class="text-base font-semibold text-gray-500 dark:text-gray-400">{{ $currency }}</span>
                    <span class="text-3xl sm:text-4xl font-bold tracking-tight text-gray-900 dark:text-gray-100 tabular-nums break-all">{{ number_format($revenue['value'], 2) }}</span>
                </p>
                <div class="flex flex-wrap items-center gap-2.5">
                    <span class="inline-flex items-center gap-1 rounded-full px-2.5 py-0.5 text-sm font-bold tabular-nums {{ $pillTone }}">
                        @if ($revenue['direction'] === 'up')
                            <svg aria-hidden="true" class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M7 17L17 7"/><path d="M8 7h9v9"/></svg>
                        @elseif ($revenue['direction'] === 'down')
                            <svg aria-hidden="true" class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M7 7l10 10"/><path d="M17 8v9H8"/></svg>
                        @endif
                        {{ $pillText }}
                    </span>
                    <span class="text-sm text-gray-500 dark:text-gray-400">{{ $revenue['note'] }}</span>
                </div>
            </div>
        </section>

        <section aria-labelledby="expenses-heading" class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 shadow-sm">
            <div class="flex items-center gap-3 px-5 sm:px-6 py-4 border-b border-gray-100 dark:border-gray-700">
                <span aria-hidden="true" class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-orange-50 dark:bg-orange-500/10 text-orange-700 dark:text-orange-400">
                    <x-icon name="expenses" class="h-5 w-5" />
                </span>
                <div>
                    <h2 id="expenses-heading" class="text-base font-semibold text-gray-900 dark:text-gray-100">{{ __('Expenses') }}</h2>
                    <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('Money spent') }}</p>
                </div>
            </div>
            <div class="px-5 sm:px-6 py-6 space-y-3">
                <p class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ __('Total expenses · all time') }}</p>
                <p class="flex flex-wrap items-baseline gap-x-2">
                    <span class="text-base font-semibold text-gray-500 dark:text-gray-400">{{ $currency }}</span>
                    <span class="text-3xl sm:text-4xl font-bold tracking-tight text-gray-900 dark:text-gray-100 tabular-nums break-all">{{ number_format((float) ($stats['total_expenses'] ?? 0), 2) }}</span>
                </p>
            </div>
        </section>
    </div>

    <div class="grid gap-4 sm:grid-cols-3">
        <x-stat-card icon="members" label="{{ __('Total Members') }}" value="{{ $stats['total_members'] ?? 0 }}" />
        <x-stat-card icon="check-circle" accent="green" label="{{ __('Active Members') }}" value="{{ $stats['active_members'] ?? 0 }}" />
        <x-stat-card icon="x-circle" accent="red" label="{{ __('Expired Members') }}" value="{{ $stats['expired_members'] ?? 0 }}" />
    </div>

    {{-- Follows the Revenue card's filter (per day / month / year, or a
         running total); the range menu in its top-right corner offers ranges
         that suit that period. --}}
    <section aria-labelledby="chart-heading" class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 shadow-sm p-5">
        <div class="flex flex-wrap items-center justify-between gap-3 mb-4">
            <div>
                <h2 id="chart-heading" class="text-sm font-semibold text-gray-800 dark:text-gray-100">{{ $this->chartTitle() }}</h2>
                <p class="text-xs text-gray-500 dark:text-gray-400">{{ __('Follows the Revenue filter above') }}</p>
            </div>

            <label class="sr-only" for="revenue-range">{{ __('Chart range') }}</label>
            {{-- Keyed by period so a filter change renders a fresh select, and
                 each option marks itself selected: swapping options in place
                 left the old select showing its first option even when the
                 chart was on another range. --}}
            <select id="revenue-range" wire:key="revenue-range-{{ $revenuePeriod }}" wire:model.live="revenueRange" class="w-full sm:w-auto rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                @foreach (\App\Livewire\AdminDashboard::CHART_RANGES[$revenuePeriod] as $value => $label)
                    <option value="{{ $value }}" @selected($revenueRange === (string) $value)>{{ __($label) }}</option>
                @endforeach
            </select>
        </div>

        {{-- Height set by the container (not a fixed canvas height) so the
             chart fills its width at every screen size. --}}
        <div wire:ignore class="relative h-56 sm:h-72 w-full min-w-0"
            x-data="revenueChart(@js($revenueSeries['labels']), @js($revenueSeries['values']))"
            x-init="init()"
        >
            <canvas x-ref="canvas" role="img" aria-label="{{ $this->chartTitle() }}"></canvas>
        </div>

        @script
        <script>
            // The Chart instance lives in this closure, NOT on the Alpine
            // component: Alpine makes its data reactive, and a reactive
            // Chart.js object recurses through its own proxies ("Maximum call
            // stack size exceeded"), which also broke resizing — the chart
            // stayed desktop-wide on a phone. Plain copies of the data are
            // handed to Chart.js for the same reason.
            Alpine.data('revenueChart', (labels, data) => {
                let chart = null;
                const plain = (value) => JSON.parse(JSON.stringify(value));

                return {
                init() {
                    const existingChart = Chart.getChart(this.$refs.canvas);

                    if (existingChart) {
                        existingChart.destroy();
                    }

                    chart = new Chart(this.$refs.canvas, {
                        type: 'line',
                        data: {
                            labels: plain(labels),
                            datasets: [{
                                label: 'Revenue',
                                data: plain(data),
                                borderColor: 'rgb(79, 70, 229)',
                                backgroundColor: 'rgba(79, 70, 229, 0.1)',
                                tension: 0.3,
                                fill: true,
                                pointRadius: data.length > 14 ? 2 : 3,
                            }],
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            plugins: {
                                legend: { display: false },
                            },
                            scales: {
                                x: { ticks: { autoSkip: true, maxRotation: 0 } },
                                y: { beginAtZero: true },
                            },
                        },
                    });

                    // Fires whenever the card filter, the range menu or a live
                    // payment update changes the series.
                    $wire.$watch('revenueSeries', (value) => {
                        const series = plain(value);
                        chart.data.labels = series.labels;
                        chart.data.datasets[0].data = series.values;
                        chart.data.datasets[0].pointRadius = series.values.length > 14 ? 2 : 3;
                        chart.update();
                    });
                },

                destroy() {
                    if (chart) {
                        chart.destroy();
                        chart = null;
                    }
                },
                };
            });
        </script>
        @endscript
    </section>

    <div>
        <h2 class="text-sm font-medium text-gray-700 dark:text-gray-200 mb-3">{{ __('Quick Actions') }}</h2>
        <div class="flex flex-wrap gap-3">
            <button wire:click="$dispatch('create-member')" class="inline-flex items-center px-4 py-2 rounded-md bg-indigo-600 text-white text-sm font-medium hover:bg-indigo-500">
                + {{ __('Add Member') }}
            </button>
            <a href="{{ route('members.index') }}" wire:navigate class="inline-flex items-center px-4 py-2 rounded-md border border-gray-300 dark:border-gray-600 text-sm font-medium text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-700">
                {{ __('Renew Member') }}
            </a>
            <a href="{{ route('members.index') }}" wire:navigate class="inline-flex items-center px-4 py-2 rounded-md border border-gray-300 dark:border-gray-600 text-sm font-medium text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-700">
                {{ __('Record Payment') }}
            </a>
            <a href="{{ route('plans.index', ['create' => 1]) }}" wire:navigate class="inline-flex items-center px-4 py-2 rounded-md border border-gray-300 dark:border-gray-600 text-sm font-medium text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-700">
                + {{ __('Add Plan') }}
            </a>
        </div>
    </div>

    <livewire:member-form />
</div>
