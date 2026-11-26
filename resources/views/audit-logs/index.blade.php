@use('App\Support\AuditActions')
@php
    $fieldClass = 'rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-100 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500';
    $labelClass = 'block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1';
    $hasFilters = collect($filters)->filter()->isNotEmpty();
@endphp

<x-app-layout>
    <div class="space-y-4">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <h1 class="text-xl font-semibold text-gray-900 dark:text-gray-100">{{ __('Audit Log') }}</h1>
                <p class="text-sm text-gray-500 dark:text-gray-400">
                    {{ __('Every recorded action across users, members, plans, payments, expenses, settings and security. Entries are kept for :months months and cannot be edited or deleted.', ['months' => \App\Models\AuditLog::RETENTION_MONTHS]) }}
                </p>
            </div>

            <a href="{{ route('audit-logs.export', request()->query()) }}"
               onclick="return confirm('{{ __('Export the entries matching the current filter as CSV?') }}')"
               class="inline-flex items-center gap-1.5 px-4 py-2 rounded-md border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-sm font-medium text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-700">
                <x-icon name="download" class="h-4 w-4" />
                {{ __('Export CSV') }}
            </a>
        </div>

        <form method="GET" action="{{ route('audit-logs.index') }}" class="rounded-lg border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 p-4">
            <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-6">
                <div class="sm:col-span-2">
                    <label for="search" class="{{ $labelClass }}">{{ __('Search') }}</label>
                    <input id="search" type="text" name="search" value="{{ $filters['search'] }}" placeholder="{{ __('User, action or description…') }}" class="w-full {{ $fieldClass }}">
                </div>

                <div>
                    <label for="module" class="{{ $labelClass }}">{{ __('Module') }}</label>
                    <select id="module" name="module" class="w-full {{ $fieldClass }}">
                        <option value="">{{ __('All modules') }}</option>
                        @foreach ($modules as $value => $label)
                            <option value="{{ $value }}" @selected($filters['module'] === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label for="action" class="{{ $labelClass }}">{{ __('Action') }}</label>
                    <select id="action" name="action" class="w-full {{ $fieldClass }}">
                        <option value="">{{ __('All actions') }}</option>
                        @foreach ($actions as $value => $label)
                            <option value="{{ $value }}" @selected($filters['action'] === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label for="user" class="{{ $labelClass }}">{{ __('User') }}</label>
                    <select id="user" name="user" class="w-full {{ $fieldClass }}">
                        <option value="">{{ __('Everyone') }}</option>
                        @foreach ($users as $user)
                            <option value="{{ $user->id }}" @selected($filters['user'] === $user->id)>
                                {{ $user->name }}{{ $user->trashed() ? ' ('.__('deactivated').')' : '' }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="grid grid-cols-2 gap-2">
                    <div>
                        <label for="from" class="{{ $labelClass }}">{{ __('From') }}</label>
                        <input id="from" type="date" name="from" value="{{ $filters['from']?->toDateString() }}" class="w-full {{ $fieldClass }}">
                    </div>
                    <div>
                        <label for="to" class="{{ $labelClass }}">{{ __('To') }}</label>
                        <input id="to" type="date" name="to" value="{{ $filters['to']?->toDateString() }}" class="w-full {{ $fieldClass }}">
                    </div>
                </div>
            </div>

            @if ($errors->any())
                <p class="mt-2 text-sm text-red-600 dark:text-red-400">{{ $errors->first() }}</p>
            @endif

            <div class="mt-3 flex items-center justify-end gap-3">
                @if ($hasFilters)
                    <a href="{{ route('audit-logs.index') }}" class="text-sm font-medium text-gray-600 dark:text-gray-300 hover:underline">{{ __('Clear filters') }}</a>
                @endif
                <button type="submit" class="px-4 py-2 rounded-md bg-indigo-600 text-sm font-medium text-white hover:bg-indigo-500">
                    {{ __('Apply') }}
                </button>
            </div>
        </form>

        <p class="text-sm text-gray-500 dark:text-gray-400">
            {{ trans_choice(':count entry|:count entries', $logs->total()) }}{{ $hasFilters ? ' '.__('match the current filter') : '' }}
        </p>

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
                            <td class="px-4 py-3 text-gray-500 dark:text-gray-400 whitespace-nowrap" title="{{ $log->created_at->toDayDateTimeString() }} UTC">{{ $log->created_at->format('M j, Y H:i') }}</td>
                            <td class="px-4 py-3 text-gray-800 dark:text-gray-100 whitespace-nowrap">
                                {{ $log->user->name }}
                                @if ($log->user->trashed())
                                    <span class="text-xs text-gray-500 dark:text-gray-400">({{ __('deactivated') }})</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap">
                                <span class="inline-flex px-2 py-0.5 rounded-full text-xs font-medium bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-300">{{ AuditActions::moduleLabel($log->module) }}</span>
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap">
                                <span class="inline-flex px-2 py-0.5 rounded text-xs font-semibold {{ AuditActions::badgeClasses($log->action) }}">{{ AuditActions::label($log->action) }}</span>
                            </td>
                            <td class="px-4 py-3 text-gray-700 dark:text-gray-200 max-w-md">{{ $log->description }}</td>
                            <td class="px-4 py-3 font-mono text-xs text-gray-500 dark:text-gray-400 whitespace-nowrap">{{ $log->ip_address ?? '—' }}</td>
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
