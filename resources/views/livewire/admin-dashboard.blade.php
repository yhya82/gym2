@php $currency = \App\Models\ApplicationSetting::current()->currency; @endphp

<div class="space-y-6">
    <div>
        <h1 class="text-xl font-semibold text-gray-900 dark:text-gray-100">{{ __('Dashboard') }}</h1>
        <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('Live overview of revenue and membership.') }}</p>
    </div>

    <div class="grid grid-cols-2 lg:grid-cols-3 gap-4">
        <x-stat-card icon="revenue" label="{{ __('Total Revenue') }}" value="{{ $currency }} {{ number_format((float) ($stats['total_revenue'] ?? 0), 2) }}" />
        <x-stat-card icon="calendar" label="{{ __('Monthly Revenue') }}" value="{{ $currency }} {{ number_format((float) ($stats['monthly_revenue'] ?? 0), 2) }}" />
        <x-stat-card icon="clock" label="{{ __('Daily Revenue') }}" value="{{ $currency }} {{ number_format((float) ($stats['daily_revenue'] ?? 0), 2) }}" />
        <x-stat-card icon="members" label="{{ __('Total Members') }}" value="{{ $stats['total_members'] ?? 0 }}" />
        <x-stat-card icon="check-circle" accent="green" label="{{ __('Active Members') }}" value="{{ $stats['active_members'] ?? 0 }}" />
        <x-stat-card icon="x-circle" accent="red" label="{{ __('Expired Members') }}" value="{{ $stats['expired_members'] ?? 0 }}" />
    </div>

    <div class="rounded-lg border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 p-5">
        <h2 class="text-sm font-medium text-gray-700 dark:text-gray-200 mb-4">{{ __('Monthly Revenue') }}</h2>

        <div wire:ignore
            x-data="revenueChart(@js(array_keys($monthlyRevenue)), @js(array_values($monthlyRevenue)))"
            x-init="init()"
        >
            <canvas x-ref="canvas" height="90"></canvas>
        </div>

        @script
        <script>
            Alpine.data('revenueChart', (labels, data) => ({
                chart: null,
                init() {
                    this.chart = new Chart(this.$refs.canvas, {
                        type: 'line',
                        data: {
                            labels: labels,
                            datasets: [{
                                label: 'Revenue',
                                data: data,
                                borderColor: 'rgb(79, 70, 229)',
                                backgroundColor: 'rgba(79, 70, 229, 0.1)',
                                tension: 0.3,
                                fill: true,
                            }],
                        },
                        options: {
                            responsive: true,
                            plugins: { legend: { display: false } },
                            scales: { y: { beginAtZero: true } },
                        },
                    });

                    $wire.$watch('monthlyRevenue', (value) => {
                        this.chart.data.labels = Object.keys(value);
                        this.chart.data.datasets[0].data = Object.values(value);
                        this.chart.update();
                    });
                },
            }));
        </script>
        @endscript
    </div>

    <div>
        <h2 class="text-sm font-medium text-gray-700 dark:text-gray-200 mb-3">{{ __('Quick Actions') }}</h2>
        <div class="flex flex-wrap gap-3">
            <a href="{{ route('members.create') }}" wire:navigate class="inline-flex items-center px-4 py-2 rounded-md bg-indigo-600 text-white text-sm font-medium hover:bg-indigo-500">
                + {{ __('Add Member') }}
            </a>
            <a href="{{ route('members.index') }}" wire:navigate class="inline-flex items-center px-4 py-2 rounded-md border border-gray-300 dark:border-gray-600 text-sm font-medium text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-700">
                {{ __('Renew Member') }}
            </a>
            <a href="{{ route('members.index') }}" wire:navigate class="inline-flex items-center px-4 py-2 rounded-md border border-gray-300 dark:border-gray-600 text-sm font-medium text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-700">
                {{ __('Record Payment') }}
            </a>
            <a href="{{ route('plans.index') }}" wire:navigate class="inline-flex items-center px-4 py-2 rounded-md border border-gray-300 dark:border-gray-600 text-sm font-medium text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-700">
                + {{ __('Add Plan') }}
            </a>
        </div>
    </div>
</div>
