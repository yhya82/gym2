@use('App\Support\AuditActions')
@use('App\Support\DeviceName')
@use('App\Http\Controllers\AuditLogController')
@php
    $fieldClass = 'rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-100 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500';
    $labelClass = 'block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1';
    $hasFilters = collect($filters)->except('signins')->filter()->isNotEmpty();
    $signinsHidden = ! $filters['signins'] && $filters['module'] !== 'auth';
    $rangeLabels = ['today' => __('Today'), '7d' => __('Last 7 days'), '30d' => __('Last 30 days')];
    // Quick-range links keep every other filter, drop typed dates and the page.
    $baseQuery = collect(request()->query())->except(['from', 'to', 'range', 'page'])->all();
    $tileClass = 'rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 px-4 py-3';
    $tileLabel = 'text-xs font-medium text-gray-500 dark:text-gray-400';
    $tileValue = 'mt-1 text-2xl font-semibold tabular-nums text-gray-900 dark:text-gray-100';
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

        {{-- Summary: what's been happening, independent of the filter below. --}}
        <dl class="grid gap-3 grid-cols-2 lg:grid-cols-4">
            <div class="{{ $tileClass }}">
                <dt class="{{ $tileLabel }}">{{ __('Entries today') }}</dt>
                <dd class="{{ $tileValue }}">{{ number_format($summary['today']) }}</dd>
            </div>
            <div class="{{ $tileClass }}">
                <dt class="{{ $tileLabel }}">{{ __('Changes · last 7 days') }}</dt>
                <dd class="{{ $tileValue }}">{{ number_format($summary['changes']) }}</dd>
            </div>
            <div class="{{ $tileClass }} relative hover:border-gray-300 dark:hover:border-gray-600">
                <dt class="{{ $tileLabel }}">
                    {{-- Stretched link: the whole tile opens the matching entries. --}}
                    <a href="{{ route('audit-logs.index', ['action' => 'failed-login', 'module' => 'auth', 'range' => '7d']) }}" class="after:absolute after:inset-0">{{ __('Failed logins · last 7 days') }}</a>
                </dt>
                <dd class="{{ $tileValue }} {{ $summary['failed'] > 0 ? '!text-red-600 dark:!text-red-400' : '' }}">{{ number_format($summary['failed']) }}</dd>
            </div>
            <div class="{{ $tileClass }} min-w-0">
                <dt class="{{ $tileLabel }}">{{ __('Most active · last 30 days') }}</dt>
                <dd class="mt-1 truncate text-base font-semibold text-gray-900 dark:text-gray-100">
                    @if ($summary['top'])
                        {{ $summary['top']['name'] }}
                        <span class="block text-xs font-normal text-gray-500 dark:text-gray-400">{{ trans_choice(':count change|:count changes', $summary['top']['count']) }}</span>
                    @else
                        <span class="text-gray-400 dark:text-gray-500">—</span>
                    @endif
                </dd>
            </div>
        </dl>

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
                        <option value="system" @selected($filters['user'] === 'system')>{{ __('System (automatic)') }}</option>
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

            <div class="mt-3 flex flex-wrap items-center justify-between gap-3">
                <div class="flex flex-wrap items-center gap-2">
                    <span class="text-xs font-medium text-gray-500 dark:text-gray-400">{{ __('Quick range') }}</span>
                    @foreach (AuditLogController::RANGES as $key => $days)
                        <a href="{{ route('audit-logs.index', [...$baseQuery, 'range' => $key]) }}"
                           @if ($filters['range'] === $key) aria-current="true" @endif
                           class="rounded-full px-3 py-1 text-xs font-medium border {{ $filters['range'] === $key
                                ? 'border-indigo-600 bg-indigo-600 text-white'
                                : 'border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-700' }}">
                            {{ $rangeLabels[$key] }}
                        </a>
                    @endforeach

                    <label class="ml-2 inline-flex items-center gap-2 text-sm text-gray-700 dark:text-gray-200">
                        <input type="checkbox" name="signins" value="1" @checked($filters['signins']) onchange="this.form.submit()"
                               class="rounded border-gray-300 dark:border-gray-600 text-indigo-600 focus:ring-indigo-500">
                        {{ __('Show sign-ins') }}
                    </label>
                </div>

                <div class="flex items-center gap-3">
                    @if ($hasFilters || $filters['signins'])
                        <a href="{{ route('audit-logs.index') }}" class="text-sm font-medium text-gray-600 dark:text-gray-300 hover:underline">{{ __('Clear filters') }}</a>
                    @endif
                    <button type="submit" class="px-4 py-2 rounded-md bg-indigo-600 text-sm font-medium text-white hover:bg-indigo-500">
                        {{ __('Apply') }}
                    </button>
                </div>
            </div>
        </form>

        <p class="text-sm text-gray-500 dark:text-gray-400">
            {{ trans_choice(':count entry|:count entries', $logs->total()) }}{{ $hasFilters ? ' '.__('match the current filter') : '' }}
            @if ($signinsHidden)
                · {{ __('sign-ins hidden') }}
                <a href="{{ route('audit-logs.index', [...collect(request()->query())->except('page')->all(), 'signins' => 1]) }}" class="font-medium text-indigo-600 dark:text-indigo-400 hover:underline">{{ __('Show them') }}</a>
            @endif
            · {{ __('Times in :tz', ['tz' => $tz]) }}
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
                        <th class="px-4 py-3">{{ __('Device · IP') }}</th>
                    </tr>
                </thead>
                @forelse ($logs as $log)
                    @php
                        $local = $log->created_at->copy()->setTimezone($tz);
                        $changes = $log->changes ?? [];
                        $subjectUrl = $log->subject_type ? $log->subjectUrl() : null;
                    @endphp
                    {{-- One tbody per entry, so its Before/After row opens with it. --}}
                    <tbody x-data="{ open: false }" class="border-t border-gray-100 dark:border-gray-700 first-of-type:border-t-0">
                        <tr>
                            <td class="px-4 py-3 text-gray-500 dark:text-gray-400 whitespace-nowrap align-top" title="{{ $local->toDayDateTimeString() }} ({{ $tz }})">{{ $local->format('M j, Y H:i') }}</td>
                            <td class="px-4 py-3 text-gray-800 dark:text-gray-100 whitespace-nowrap align-top">
                                @if ($log->isSystem())
                                    <span class="inline-flex items-center gap-1 rounded px-1.5 py-0.5 text-xs font-semibold bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-300">{{ __('System') }}</span>
                                @else
                                    {{ $log->user?->name }}
                                    @if ($log->user?->trashed())
                                        <span class="text-xs text-gray-500 dark:text-gray-400">({{ __('deactivated') }})</span>
                                    @endif
                                @endif
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap align-top">
                                <span class="inline-flex px-2 py-0.5 rounded-full text-xs font-medium bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-300">{{ AuditActions::moduleLabel($log->module) }}</span>
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap align-top">
                                <span class="inline-flex px-2 py-0.5 rounded text-xs font-semibold {{ AuditActions::badgeClasses($log->action) }}">{{ AuditActions::label($log->action) }}</span>
                            </td>
                            <td class="px-4 py-3 text-gray-700 dark:text-gray-200 max-w-md align-top">
                                <p class="break-words">{{ $log->description }}</p>
                                @if ($changes || $subjectUrl)
                                    <div class="mt-1 flex flex-wrap items-center gap-x-3 gap-y-1 text-xs font-medium">
                                        @if ($changes)
                                            <button type="button" x-on:click="open = ! open" x-bind:aria-expanded="open"
                                                    aria-controls="changes-{{ $log->id }}"
                                                    class="inline-flex items-center gap-1 text-indigo-600 dark:text-indigo-400 hover:underline">
                                                <span x-text="open ? @js(__('Hide changes')) : @js(trans_choice('Show change|Show :count changes', count($changes)))">{{ trans_choice('Show change|Show :count changes', count($changes)) }}</span>
                                                <svg class="h-3 w-3 transition-transform" x-bind:class="open && 'rotate-180'" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="6 9 12 15 18 9"/></svg>
                                            </button>
                                        @endif
                                        @if ($subjectUrl)
                                            <a href="{{ $subjectUrl }}" class="text-gray-600 dark:text-gray-300 hover:text-indigo-600 dark:hover:text-indigo-400 hover:underline">{{ __('Open record') }} →</a>
                                        @endif
                                    </div>
                                @endif
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap align-top">
                                @if ($log->user_agent)
                                    <span class="block text-xs text-gray-700 dark:text-gray-200">{{ DeviceName::from($log->user_agent) }}</span>
                                @endif
                                <span class="block font-mono text-xs text-gray-500 dark:text-gray-400">{{ $log->ip_address ?? '—' }}</span>
                            </td>
                        </tr>
                        @if ($changes)
                            <tr id="changes-{{ $log->id }}" x-show="open" x-cloak>
                                <td colspan="6" class="px-4 pb-4 pt-0">
                                    <div class="ml-auto max-w-3xl overflow-hidden rounded-lg border border-gray-200 dark:border-gray-700">
                                        <table class="min-w-full text-xs">
                                            <thead class="bg-gray-50 dark:bg-gray-900/40 text-left text-gray-500 dark:text-gray-400">
                                                <tr>
                                                    <th class="px-3 py-2 font-medium w-1/4">{{ __('Field') }}</th>
                                                    <th class="px-3 py-2 font-medium">{{ __('Before') }}</th>
                                                    <th class="px-3 py-2 font-medium">{{ __('After') }}</th>
                                                </tr>
                                            </thead>
                                            <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                                                @foreach ($changes as $field => $change)
                                                    <tr>
                                                        <th scope="row" class="px-3 py-2 text-left font-medium text-gray-700 dark:text-gray-200">{{ AuditActions::fieldLabel($field) }}</th>
                                                        <td class="px-3 py-2 break-all">
                                                            @if (($change['old'] ?? null) === null || $change['old'] === '')
                                                                <span class="text-gray-400 dark:text-gray-500">{{ __('empty') }}</span>
                                                            @else
                                                                <span class="rounded bg-red-50 px-1.5 py-0.5 text-red-800 line-through decoration-red-300 dark:bg-red-500/10 dark:text-red-300">{{ $change['old'] }}</span>
                                                            @endif
                                                        </td>
                                                        <td class="px-3 py-2 break-all">
                                                            @if (($change['new'] ?? null) === null || $change['new'] === '')
                                                                <span class="text-gray-400 dark:text-gray-500">{{ __('empty') }}</span>
                                                            @else
                                                                <span class="rounded bg-green-50 px-1.5 py-0.5 text-green-800 dark:bg-green-500/10 dark:text-green-300">{{ $change['new'] }}</span>
                                                            @endif
                                                        </td>
                                                    </tr>
                                                @endforeach
                                            </tbody>
                                        </table>
                                    </div>
                                </td>
                            </tr>
                        @endif
                    </tbody>
                @empty
                    <tbody>
                        <tr><td colspan="6" class="px-4 py-10 text-center text-gray-400 dark:text-gray-500">{{ __('No audit log entries found.') }}</td></tr>
                    </tbody>
                @endforelse
            </table>
        </div>

        {{ $logs->links() }}
    </div>
</x-app-layout>
