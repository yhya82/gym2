@php $currency = \App\Models\ApplicationSetting::current()->currency; @endphp

<div class="space-y-6">
    <div>
        <h1 class="text-xl font-semibold text-gray-900 dark:text-gray-100">{{ __('Dashboard') }}</h1>
        <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('Live overview of revenue and membership.') }}</p>
    </div>

    @php
        $revenue = $this->revenueCard;
        $periodLabels = ['daily' => __('Daily'), 'monthly' => __('Monthly'), 'yearly' => __('Yearly'), 'total' => __('Total')];
        // The "Payments" stat's note — which window the count covers.
        $periodNotes = ['daily' => __('today'), 'monthly' => __('this month'), 'yearly' => __('this year'), 'total' => __('all time')];
        $outstanding = $this->outstanding;
        $pillTone = match ($revenue['tone']) {
            'good' => 'bg-green-100 text-green-800 dark:bg-green-500/15 dark:text-green-300',
            'bad' => 'bg-red-100 text-red-800 dark:bg-red-500/15 dark:text-red-300',
            default => 'bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-200',
        };
        $pillText = $revenue['pct'] === null
            ? __('New')
            : ($revenue['pct'] > 0 ? '+' : ($revenue['pct'] < 0 ? '−' : '')).number_format(abs($revenue['pct']), 1).'%';
    @endphp

    {{-- Revenue (2/3) + Expenses (1/3) on large screens; stacked below that.
         Both cards: period badge on top, the figure centred, a live line at
         the bottom. The Revenue card adds a stats panel on the right, which
         drops underneath on narrow screens. --}}
    <div class="grid gap-5 lg:grid-cols-3">
        <section aria-labelledby="revenue-heading" class="lg:col-span-2 flex flex-col rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 shadow-sm">
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

            <div class="flex flex-1 flex-wrap items-stretch gap-x-8 gap-y-5 p-5 sm:p-6" wire:loading.class="opacity-60" wire:target="setRevenuePeriod">
                {{-- Left: period, figure + pill, live line --}}
                <div class="flex min-w-0 flex-1 basis-64 flex-col gap-3.5">
                    <span class="inline-flex items-center gap-2 self-start rounded-full bg-gray-100 dark:bg-gray-700 px-2.5 py-1 text-xs font-semibold text-gray-700 dark:text-gray-200">
                        <x-icon name="calendar" class="h-3.5 w-3.5" aria-hidden="true" />
                        {{ $revenue['label'] }}
                    </span>

                    <div class="flex flex-1 flex-col justify-center gap-1.5">
                        <p class="text-sm font-medium text-gray-500 dark:text-gray-400">{{ __('Revenue') }}</p>
                        <div class="flex flex-wrap items-center gap-3">
                            <p class="inline-flex items-baseline gap-1.5">
                                <span class="text-sm font-semibold text-gray-500 dark:text-gray-400">{{ $currency }}</span>
                                <span class="text-3xl sm:text-4xl font-bold tracking-tight text-gray-900 dark:text-gray-100 tabular-nums break-all">{{ number_format($revenue['value'], 2) }}</span>
                            </p>
                            <span class="inline-flex items-center gap-1 rounded-full px-2.5 py-0.5 text-sm font-bold tabular-nums {{ $pillTone }}">
                                @if ($revenue['direction'] === 'up')
                                    <svg aria-hidden="true" class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M7 17L17 7"/><path d="M8 7h9v9"/></svg>
                                @elseif ($revenue['direction'] === 'down')
                                    <svg aria-hidden="true" class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M7 7l10 10"/><path d="M17 8v9H8"/></svg>
                                @endif
                                {{ $pillText }}
                            </span>
                        </div>
                        <p class="text-sm text-gray-500 dark:text-gray-400">{{ $revenue['note'] }}</p>
                    </div>

                    <p class="flex items-center gap-2 border-t border-dashed border-gray-200 dark:border-gray-700 pt-3 text-xs text-gray-500 dark:text-gray-400">
                        <span aria-hidden="true" class="h-2 w-2 rounded-full bg-green-500 ring-[3px] ring-green-100 dark:ring-green-500/20"></span>
                        {{ __('Live · updates as payments are recorded') }}
                    </p>
                </div>

                {{-- Right: quick stats --}}
                <dl class="flex flex-1 basis-60 flex-col self-center rounded-xl bg-gray-50 dark:bg-gray-900/40 sm:max-w-sm">
                    @foreach ([
                        ['label' => __('Outstanding'), 'value' => $currency.' '.number_format($outstanding['amount'], 2),
                         'note' => trans_choice(':count member owes|:count members owe', $outstanding['members']),
                         'icon' => 'clock', 'tile' => 'bg-amber-50 dark:bg-amber-500/10 text-amber-700 dark:text-amber-400',
                         'noteClass' => $outstanding['amount'] > 0 ? 'font-semibold text-amber-800 dark:text-amber-300' : 'text-gray-500 dark:text-gray-400'],
                        ['label' => __('Payments'), 'value' => number_format($revenue['payments']),
                         'note' => $periodNotes[$revenuePeriod],
                         'icon' => 'check-circle', 'tile' => 'bg-indigo-50 dark:bg-indigo-500/10 text-indigo-600 dark:text-indigo-400',
                         'noteClass' => 'text-gray-500 dark:text-gray-400'],
                        ['label' => __('Average payment'), 'value' => $currency.' '.number_format($revenue['average'], 2),
                         'note' => __('per payment'),
                         'icon' => 'revenue', 'tile' => 'bg-emerald-50 dark:bg-emerald-500/10 text-emerald-700 dark:text-emerald-400',
                         'noteClass' => 'text-gray-500 dark:text-gray-400'],
                    ] as $i => $stat)
                        <div @class(['flex items-center gap-3 px-4 py-3', 'border-t border-gray-200 dark:border-gray-700' => $i > 0])>
                            <span aria-hidden="true" class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg {{ $stat['tile'] }}">
                                <x-icon :name="$stat['icon']" class="h-4 w-4" />
                            </span>
                            <div class="min-w-0 flex-1">
                                <dt class="text-xs text-gray-500 dark:text-gray-400">{{ $stat['label'] }}</dt>
                                <dd class="mt-0.5 text-base font-bold text-gray-900 dark:text-gray-100 tabular-nums">{{ $stat['value'] }}</dd>
                            </div>
                            <span class="whitespace-nowrap text-right text-xs {{ $stat['noteClass'] }}">{{ $stat['note'] }}</span>
                        </div>
                    @endforeach
                </dl>
            </div>
        </section>

        <section aria-labelledby="expenses-heading" class="flex flex-col rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 shadow-sm">
            <div class="flex items-center gap-3 px-5 sm:px-6 py-4 border-b border-gray-100 dark:border-gray-700">
                <span aria-hidden="true" class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-orange-50 dark:bg-orange-500/10 text-orange-700 dark:text-orange-400">
                    <x-icon name="expenses" class="h-5 w-5" />
                </span>
                <div>
                    <h2 id="expenses-heading" class="text-base font-semibold text-gray-900 dark:text-gray-100">{{ __('Expenses') }}</h2>
                    <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('Money spent') }}</p>
                </div>
            </div>
            <div class="flex flex-1 flex-col gap-3.5 p-5 sm:p-6">
                <span class="inline-flex items-center gap-2 self-start rounded-full bg-gray-100 dark:bg-gray-700 px-2.5 py-1 text-xs font-semibold text-gray-700 dark:text-gray-200">
                    <x-icon name="calendar" class="h-3.5 w-3.5" aria-hidden="true" />
                    {{ __('All time') }}
                </span>
                <div class="flex flex-1 flex-col justify-center gap-1.5">
                    <p class="text-sm font-medium text-gray-500 dark:text-gray-400">{{ __('Total expenses') }}</p>
                    <p class="inline-flex items-baseline gap-1.5">
                        <span class="text-sm font-semibold text-gray-500 dark:text-gray-400">{{ $currency }}</span>
                        <span class="text-3xl sm:text-4xl font-bold tracking-tight text-gray-900 dark:text-gray-100 tabular-nums break-all">{{ number_format((float) ($stats['total_expenses'] ?? 0), 2) }}</span>
                    </p>
                </div>
                <p class="flex items-center gap-2 border-t border-dashed border-gray-200 dark:border-gray-700 pt-3 text-xs text-gray-500 dark:text-gray-400">
                    <span aria-hidden="true" class="h-2 w-2 rounded-full bg-green-500 ring-[3px] ring-green-100 dark:ring-green-500/20"></span>
                    {{ __('Live · updates as expenses are logged') }}
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
    {{-- Chart (3/4) + Quick Actions stacked beside it (1/4) on large
         screens; Quick Actions drop underneath on smaller ones. --}}
    <div class="grid gap-5 lg:grid-cols-4">
    <section aria-labelledby="chart-heading" class="lg:col-span-3 min-w-0 rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 shadow-sm p-5">
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
        <div wire:ignore class="relative h-48 sm:h-56 w-full min-w-0"
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

    {{-- No card behind the buttons; plain white buttons, the colour lives in
         each icon tile. On large screens they stretch to the chart's height. --}}
    @php
        $actionClass = 'flex flex-1 items-center gap-2.5 min-h-[44px] rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 px-3.5 text-sm font-semibold text-gray-800 dark:text-gray-100 hover:border-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500 transition-colors';
        $tileClass = 'flex h-7 w-7 shrink-0 items-center justify-center rounded-lg';
    @endphp
    <section aria-labelledby="quick-actions-heading" class="flex flex-col gap-2.5">
        <h2 id="quick-actions-heading" class="text-sm font-semibold text-gray-800 dark:text-gray-100">{{ __('Quick Actions') }}</h2>
        <div class="grid flex-1 gap-2 sm:grid-cols-2 lg:flex lg:flex-col">
            <button type="button" wire:click="$dispatch('create-member')" class="{{ $actionClass }}">
                <span aria-hidden="true" class="{{ $tileClass }} bg-indigo-50 dark:bg-indigo-500/15 text-indigo-600 dark:text-indigo-300"><x-icon name="user-plus" class="h-4 w-4" /></span>
                {{ __('Add Member') }}
            </button>
            <a href="{{ route('members.index') }}" wire:navigate class="{{ $actionClass }}">
                <span aria-hidden="true" class="{{ $tileClass }} bg-emerald-100 dark:bg-emerald-500/15 text-emerald-700 dark:text-emerald-300"><x-icon name="refresh" class="h-4 w-4" /></span>
                {{ __('Renew Member') }}
            </a>
            <a href="{{ route('members.index') }}" wire:navigate class="{{ $actionClass }}">
                <span aria-hidden="true" class="{{ $tileClass }} bg-sky-100 dark:bg-sky-500/15 text-sky-700 dark:text-sky-300"><x-icon name="payments" class="h-4 w-4" /></span>
                {{ __('Record Payment') }}
            </a>
            <a href="{{ route('plans.index', ['create' => 1]) }}" wire:navigate class="{{ $actionClass }}">
                <span aria-hidden="true" class="{{ $tileClass }} bg-violet-100 dark:bg-violet-500/15 text-violet-700 dark:text-violet-300"><x-icon name="plus-list" class="h-4 w-4" /></span>
                {{ __('Add Plan') }}
            </a>
        </div>
    </section>
    </div>

    <livewire:member-form />
</div>
