<x-app-layout>
    <div class="space-y-4">
        <div>
            <h1 class="text-xl font-semibold text-gray-900 dark:text-gray-100">{{ __('Audit Log') }}</h1>
            <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('Every logged action across members, plans, payments, settings, and authentication.') }}</p>
        </div>

        <form method="GET" action="{{ route('audit-logs.index') }}" class="flex flex-wrap gap-3">
            <input type="text" name="search" value="{{ request('search') }}" placeholder="{{ __('Search by user, action, or description…') }}" class="flex-1 min-w-[240px] rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-100 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">

            <select name="module" onchange="this.form.submit()" class="rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-100 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                <option value="">{{ __('All Modules') }}</option>
                @foreach ($modules as $module)
                    <option value="{{ $module }}" @selected(request('module') === $module)>{{ ucfirst($module) }}</option>
                @endforeach
            </select>

            <button type="submit" class="px-4 py-2 rounded-md border border-gray-300 dark:border-gray-600 text-sm font-medium text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-700">
                {{ __('Filter') }}
            </button>
        </form>

        <div class="overflow-x-auto rounded-lg border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800">
            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700 text-sm">
                <thead class="bg-gray-50 dark:bg-gray-700/50">
                    <tr class="text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                        <th class="px-4 py-3">{{ __('Date') }}</th>
                        <th class="px-4 py-3">{{ __('User') }}</th>
                        <th class="px-4 py-3">{{ __('Module') }}</th>
                        <th class="px-4 py-3">{{ __('Action') }}</th>
                        <th class="px-4 py-3">{{ __('Description') }}</th>
                        <th class="px-4 py-3">{{ __('IP Address') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                    @forelse ($logs as $log)
                        <tr>
                            <td class="px-4 py-3 text-gray-500 dark:text-gray-400 whitespace-nowrap">{{ $log->created_at->format('M j, Y H:i') }}</td>
                            <td class="px-4 py-3 text-gray-800 dark:text-gray-100">{{ $log->user?->name ?? __('Deleted user') }}</td>
                            <td class="px-4 py-3">
                                <span class="inline-flex px-2 py-0.5 rounded-full text-xs font-medium bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-300">{{ ucfirst($log->module) }}</span>
                            </td>
                            <td class="px-4 py-3 text-gray-500 dark:text-gray-400">{{ ucfirst($log->action) }}</td>
                            <td class="px-4 py-3 text-gray-700 dark:text-gray-200 max-w-md">{{ $log->description }}</td>
                            <td class="px-4 py-3 text-gray-400 dark:text-gray-500 whitespace-nowrap">{{ $log->ip_address ?? '—' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="px-4 py-10 text-center text-gray-400 dark:text-gray-500">{{ __('No audit log entries found.') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{ $logs->links() }}
    </div>
</x-app-layout>
