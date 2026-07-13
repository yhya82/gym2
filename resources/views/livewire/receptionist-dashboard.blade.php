<div class="space-y-6">
    <div>
        <h1 class="text-xl font-semibold text-gray-900 dark:text-gray-100">{{ __('Dashboard') }}</h1>
        <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('Live overview of membership.') }}</p>
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
        <x-stat-card icon="members" label="{{ __('Total Members') }}" value="{{ $stats['total_members'] ?? 0 }}" />
        <x-stat-card icon="check-circle" accent="green" label="{{ __('Active Members') }}" value="{{ $stats['active_members'] ?? 0 }}" />
        <x-stat-card icon="x-circle" accent="red" label="{{ __('Expired Members') }}" value="{{ $stats['expired_members'] ?? 0 }}" />
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
        </div>
    </div>
</div>
