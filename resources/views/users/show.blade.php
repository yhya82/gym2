@use('App\Support\AuditActions')

<x-app-layout>
    <div class="max-w-4xl space-y-6">
        <a href="{{ route('users.index') }}" wire:navigate class="inline-flex items-center gap-1 text-sm font-medium text-gray-500 dark:text-gray-400 hover:text-indigo-600 dark:hover:text-indigo-400">
            <svg aria-hidden="true" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" /></svg>
            {{ __('Back to Users') }}
        </a>

        <livewire:profile-details :user="$user" />

        <div class="grid gap-6 lg:grid-cols-3">
            <section aria-labelledby="status-heading" class="rounded-lg border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 p-5">
                <h2 id="status-heading" class="text-sm font-semibold text-gray-900 dark:text-gray-100">{{ __('Account status') }}</h2>
                <dl class="mt-3 space-y-3 text-sm">
                    <div class="flex justify-between gap-3">
                        <dt class="text-gray-500 dark:text-gray-400">{{ __('Sign-in') }}</dt>
                        <dd>
                            <span @class([
                                'inline-flex items-center gap-1.5 rounded-full px-2.5 py-0.5 text-xs font-semibold',
                                'bg-amber-100 text-amber-800 dark:bg-amber-500/15 dark:text-amber-300' => $user->isLocked(),
                                'bg-green-100 text-green-800 dark:bg-green-500/15 dark:text-green-300' => ! $user->isLocked(),
                            ])>
                                <span aria-hidden="true" @class(['h-1.5 w-1.5 rounded-full', 'bg-amber-500' => $user->isLocked(), 'bg-green-500' => ! $user->isLocked()])></span>
                                {{ $user->isLocked() ? __('Locked until :time', ['time' => $user->locked_until->format('H:i')]) : __('Active') }}
                            </span>
                        </dd>
                    </div>
                    <div class="flex justify-between gap-3">
                        <dt class="text-gray-500 dark:text-gray-400">{{ __('Failed attempts') }}</dt>
                        <dd class="font-medium text-gray-900 dark:text-gray-100 tabular-nums">{{ $user->failed_login_attempts }}</dd>
                    </div>
                    <div class="flex justify-between gap-3">
                        <dt class="text-gray-500 dark:text-gray-400">{{ __('Password') }}</dt>
                        <dd>
                            <span @class([
                                'inline-flex items-center gap-1.5 rounded-full px-2.5 py-0.5 text-xs font-semibold',
                                'bg-amber-100 text-amber-800 dark:bg-amber-500/15 dark:text-amber-300' => $user->must_change_password,
                                'bg-green-100 text-green-800 dark:bg-green-500/15 dark:text-green-300' => ! $user->must_change_password,
                            ])>
                                <span aria-hidden="true" @class(['h-1.5 w-1.5 rounded-full', 'bg-amber-500' => $user->must_change_password, 'bg-green-500' => ! $user->must_change_password])></span>
                                {{ $user->must_change_password ? __('Must change at next login') : __('OK') }}
                            </span>
                        </dd>
                    </div>
                </dl>
                @if ($user->isLocked())
                    <a href="{{ route('settings.security', ['tab' => 'locked']) }}" wire:navigate class="mt-4 inline-block text-sm font-medium text-indigo-600 dark:text-indigo-400 hover:underline">{{ __('Unlock in Security Center') }} →</a>
                @endif
            </section>

            <section aria-labelledby="activity-heading" class="lg:col-span-2 rounded-lg border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 overflow-hidden">
                <div class="flex items-center justify-between px-5 py-4 border-b border-gray-100 dark:border-gray-700">
                    <h2 id="activity-heading" class="text-sm font-semibold text-gray-900 dark:text-gray-100">{{ __('Recent activity') }}</h2>
                    <a href="{{ route('audit-logs.index', ['user' => $user->id]) }}" wire:navigate class="text-sm font-medium text-indigo-600 dark:text-indigo-400 hover:underline">{{ __('Full history') }} →</a>
                </div>
                <ul>
                    @forelse ($recentActivity as $log)
                        <li class="flex flex-wrap items-center gap-x-3 gap-y-1 px-5 py-3 border-b border-gray-100 dark:border-gray-700 last:border-0">
                            <span class="min-w-[88px] rounded px-2 py-0.5 text-center text-xs font-semibold {{ AuditActions::badgeClasses($log->action) }}">{{ AuditActions::label($log->action) }}</span>
                            <span class="flex-1 text-sm text-gray-700 dark:text-gray-200">{{ $log->description }}</span>
                            <span class="whitespace-nowrap text-xs text-gray-500 dark:text-gray-400" title="{{ $log->created_at->toDayDateTimeString() }} UTC">{{ $log->created_at->diffForHumans() }}</span>
                        </li>
                    @empty
                        <li class="px-5 py-8 text-center text-sm text-gray-500 dark:text-gray-400">{{ __('No activity recorded yet.') }}</li>
                    @endforelse
                </ul>
            </section>
        </div>
    </div>

    <livewire:change-password-modal />
</x-app-layout>
