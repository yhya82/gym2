@php
    $score = $this->score;
    $stats = $this->stats;
    $policy = $this->policy;

    $gradeClasses = match ($score['grade']) {
        'Strong' => 'bg-green-100 text-green-800 dark:bg-green-500/10 dark:text-green-400',
        'Fair' => 'bg-amber-100 text-amber-800 dark:bg-amber-500/10 dark:text-amber-400',
        default => 'bg-red-100 text-red-800 dark:bg-red-500/10 dark:text-red-400',
    };
    $ringColor = match ($score['grade']) {
        'Strong' => '#16A34A',
        'Fair' => '#D97706',
        default => '#DC2626',
    };
    // Ring circumference for r=28.
    $circumference = 175.93;
    $dash = round($circumference * $score['score'] / 100, 2);

    $tabs = [
        'overview' => __('Overview'),
        'policy' => __('Password Policy'),
        'locked' => __('Locked Accounts'),
        'sessions' => __('Active Sessions'),
        'events' => __('Security Events'),
    ];

    $cardClass = 'rounded-lg border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800';
    $inputClass = 'block w-28 rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500';
    $rowClass = 'flex flex-wrap items-center gap-x-8 gap-y-3 px-6 py-4 border-b border-gray-100 dark:border-gray-700';
@endphp

<div class="space-y-6">
    {{-- Heading + security score --}}
    <div class="flex flex-wrap items-center justify-between gap-4">
        <div>
            <h1 class="text-xl font-semibold text-gray-900 dark:text-gray-100">{{ __('Settings') }}</h1>
            <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('Password policy, account lockouts and signed-in sessions.') }}</p>
        </div>

        <div class="flex items-center gap-4 {{ $cardClass }} py-3 pl-3 pr-5">
            <svg role="img" aria-label="{{ __('Security score :score out of 100', ['score' => $score['score']]) }}" width="64" height="64" viewBox="0 0 64 64">
                <circle cx="32" cy="32" r="28" fill="none" stroke-width="6" class="stroke-gray-100 dark:stroke-gray-700"></circle>
                <circle cx="32" cy="32" r="28" fill="none" stroke="{{ $ringColor }}" stroke-width="6" stroke-linecap="round"
                        stroke-dasharray="{{ $dash }} {{ $circumference }}" transform="rotate(-90 32 32)"></circle>
                <text x="32" y="37" text-anchor="middle" font-size="17" font-weight="700" class="fill-gray-900 dark:fill-gray-100">{{ $score['score'] }}</text>
            </svg>
            <div class="flex flex-col gap-0.5">
                <span class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ __('Security score') }}</span>
                <span class="text-base font-bold text-gray-900 dark:text-gray-100">
                    {{ $score['score'] }} / 100
                    <span class="ml-1 rounded-full px-2 py-0.5 text-xs font-semibold {{ $gradeClasses }}">{{ __($score['grade']) }}</span>
                </span>
                @if (count($score['recommendations']))
                    <button type="button" wire:click="selectTab('overview')" class="text-left text-sm font-medium text-indigo-600 dark:text-indigo-400 hover:underline">
                        {{ trans_choice(':count improvement available|:count improvements available', count($score['recommendations'])) }} →
                    </button>
                @else
                    <span class="text-sm text-green-700 dark:text-green-400">{{ __('Policy meets every recommendation') }}</span>
                @endif
            </div>
        </div>
    </div>

    @include('settings.partials.tabs', ['current' => 'settings.security'])

    {{-- Stat cards --}}
    <section aria-label="{{ __('Login and session statistics') }}" class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
        @foreach ([
            ['label' => __('Total logins'), 'value' => $stats['total_logins'], 'hint' => __('All attempts, all time'), 'color' => 'text-gray-900 dark:text-gray-100'],
            ['label' => __('Successful logins'), 'value' => $stats['successful_logins'], 'hint' => $stats['total_logins'] ? __(':pct% of attempts', ['pct' => round($stats['successful_logins'] / $stats['total_logins'] * 100, 1)]) : __('No attempts yet'), 'color' => 'text-green-700 dark:text-green-400'],
            ['label' => __('Failed logins'), 'value' => $stats['failed_logins'], 'hint' => __('Includes unknown emails'), 'color' => 'text-red-700 dark:text-red-400'],
            ['label' => __('Accounts locked right now'), 'value' => $stats['locked_accounts'], 'hint' => __('See Locked Accounts'), 'color' => 'text-amber-700 dark:text-amber-400'],
            ['label' => __('Active sessions'), 'value' => $stats['active_sessions'], 'hint' => __('Not yet timed out'), 'color' => 'text-gray-900 dark:text-gray-100'],
            ['label' => __('Currently online'), 'value' => $stats['online_users'], 'hint' => __('Active in the last 5 minutes'), 'color' => 'text-gray-900 dark:text-gray-100'],
        ] as $card)
            <div class="{{ $cardClass }} p-5">
                <p class="text-sm font-medium text-gray-500 dark:text-gray-400">{{ $card['label'] }}</p>
                <p class="mt-2 text-3xl font-bold tabular-nums {{ $card['color'] }}">{{ number_format($card['value']) }}</p>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ $card['hint'] }}</p>
            </div>
        @endforeach
    </section>

    {{-- Security Center tabs --}}
    <div role="tablist" aria-label="{{ __('Security Center sections') }}" class="flex flex-wrap gap-1 {{ $cardClass }} p-1">
        @foreach ($tabs as $key => $label)
            <button
                type="button"
                role="tab"
                aria-selected="{{ $tab === $key ? 'true' : 'false' }}"
                wire:click="selectTab('{{ $key }}')"
                @class([
                    'inline-flex items-center gap-2 min-h-[44px] px-4 rounded-md text-sm',
                    'bg-indigo-50 dark:bg-indigo-500/10 text-indigo-700 dark:text-indigo-300 font-semibold' => $tab === $key,
                    'text-gray-600 dark:text-gray-300 font-medium hover:bg-gray-50 dark:hover:bg-gray-700' => $tab !== $key,
                ])
            >
                {{ $label }}
                @if ($key === 'locked' && $stats['locked_accounts'])
                    <span class="rounded-full bg-amber-100 dark:bg-amber-500/10 px-2 text-xs font-bold text-amber-800 dark:text-amber-400">{{ $stats['locked_accounts'] }}</span>
                @endif
            </button>
        @endforeach
    </div>

    {{-- ============================== Overview ============================== --}}
    @if ($tab === 'overview')
        <div role="tabpanel" class="grid gap-4 lg:grid-cols-3">
            <section class="{{ $cardClass }} lg:col-span-2">
                <div class="flex items-center justify-between gap-3 px-6 py-4 border-b border-gray-100 dark:border-gray-700">
                    <div>
                        <h2 class="text-base font-semibold text-gray-900 dark:text-gray-100">{{ __('Improve your password policy') }}</h2>
                        <p class="text-sm text-gray-500 dark:text-gray-400">
                            @if (count($score['recommendations']))
                                {{ __('Apply these to reach a score of 100.') }}
                            @else
                                {{ __('Nothing left to improve.') }}
                            @endif
                        </p>
                    </div>
                    @if (count($score['recommendations']))
                        <span class="whitespace-nowrap rounded-full bg-green-100 dark:bg-green-500/10 px-3 py-1 text-sm font-semibold text-green-800 dark:text-green-400">
                            +{{ 100 - $score['score'] }} {{ __('points available') }}
                        </span>
                    @endif
                </div>

                <ul class="px-6">
                    @foreach ($score['recommendations'] as $rec)
                        <li class="flex items-center gap-4 py-3.5 border-b border-gray-100 dark:border-gray-700 last:border-0">
                            <span class="w-10 text-sm font-bold text-green-700 dark:text-green-400">+{{ $rec['points'] }}</span>
                            <div class="flex-1">
                                <p class="text-sm font-medium text-gray-900 dark:text-gray-100">{{ __($rec['title']) }}</p>
                                <p class="text-sm text-gray-500 dark:text-gray-400">{{ __($rec['detail']) }}</p>
                            </div>
                            <button type="button" wire:click="fixPolicy" class="min-h-[36px] px-3.5 rounded-md border border-indigo-200 dark:border-indigo-500/30 bg-indigo-50 dark:bg-indigo-500/10 text-sm font-semibold text-indigo-700 dark:text-indigo-300 hover:bg-indigo-100">
                                {{ __('Fix') }}
                            </button>
                        </li>
                    @endforeach
                </ul>

                @if (count($score['passing']))
                    <div class="flex flex-wrap gap-x-6 gap-y-1 px-6 py-3.5 border-t border-gray-100 dark:border-gray-700 bg-gray-50 dark:bg-gray-700/40 rounded-b-lg text-sm text-gray-600 dark:text-gray-300">
                        @foreach ($score['passing'] as $item)
                            <span><span class="font-semibold text-green-700 dark:text-green-400">✓</span> {{ __($item) }}</span>
                        @endforeach
                    </div>
                @endif
            </section>

            <section class="{{ $cardClass }}">
                <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100 dark:border-gray-700">
                    <h2 class="text-base font-semibold text-gray-900 dark:text-gray-100">{{ __('Needs attention') }}</h2>
                    <button type="button" wire:click="selectTab('locked')" class="text-sm font-medium text-indigo-600 dark:text-indigo-400 hover:underline">{{ __('View all') }}</button>
                </div>
                <ul class="px-6 py-2">
                    @forelse ($this->lockedUsers->take(5) as $user)
                        <li wire:key="att-{{ $user->id }}" class="flex items-center justify-between gap-3 py-3 border-b border-gray-100 dark:border-gray-700 last:border-0">
                            <div>
                                <p class="text-sm font-medium text-gray-900 dark:text-gray-100">{{ $user->name }}</p>
                                <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('Locked · unlocks :when', ['when' => $user->locked_until->diffForHumans()]) }}</p>
                            </div>
                            <button type="button" wire:click="unlock({{ $user->id }})" class="min-h-[36px] px-3 rounded-md border border-indigo-200 dark:border-indigo-500/30 bg-indigo-50 dark:bg-indigo-500/10 text-sm font-semibold text-indigo-700 dark:text-indigo-300">{{ __('Unlock') }}</button>
                        </li>
                    @empty
                        <li class="py-6 text-center text-sm text-gray-500 dark:text-gray-400">{{ __('No locked accounts.') }}</li>
                    @endforelse
                </ul>
            </section>

            <section class="{{ $cardClass }} lg:col-span-3 overflow-hidden">
                <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100 dark:border-gray-700">
                    <h2 class="text-base font-semibold text-gray-900 dark:text-gray-100">{{ __('Latest security events') }}</h2>
                    <button type="button" wire:click="selectTab('events')" class="text-sm font-medium text-indigo-600 dark:text-indigo-400 hover:underline">{{ __('View all events') }} →</button>
                </div>
                @include('livewire.partials.security-event-list', ['events' => $this->recentEvents, 'badgeFor' => fn ($action) => $this->eventBadge($action)])
            </section>
        </div>
    @endif

    {{-- =========================== Password Policy =========================== --}}
    @if ($tab === 'policy')
        <section role="tabpanel" aria-labelledby="policy-heading" class="{{ $cardClass }} overflow-hidden"
                 x-data="{ saved: false }" x-on:security-policy-saved.window="saved = true; setTimeout(() => saved = false, 3000)">
            <div class="flex items-center justify-between gap-4 px-6 py-5 border-b border-gray-200 dark:border-gray-700">
                <div>
                    <div class="flex items-center gap-2.5">
                        <h2 id="policy-heading" class="text-base font-semibold text-gray-900 dark:text-gray-100">{{ __('Password Policy') }}</h2>
                        @if ($editing)
                            <span class="rounded-full bg-indigo-100 dark:bg-indigo-500/10 px-2 py-0.5 text-xs font-semibold text-indigo-700 dark:text-indigo-300">{{ __('Editing') }}</span>
                        @endif
                    </div>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ __('Applies whenever a password is created, reset or changed.') }}</p>
                </div>
                <button
                    type="button"
                    wire:click="{{ $editing ? 'cancelEditing' : 'startEditing' }}"
                    aria-label="{{ $editing ? __('Stop editing password policy') : __('Edit password policy') }}"
                    title="{{ $editing ? __('Stop editing') : __('Edit policy') }}"
                    aria-pressed="{{ $editing ? 'true' : 'false' }}"
                    @class([
                        'flex h-11 w-11 shrink-0 items-center justify-center rounded-lg border',
                        'border-indigo-600 bg-indigo-600 text-white' => $editing,
                        'border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-700' => ! $editing,
                    ])
                >
                    <svg aria-hidden="true" class="h-[18px] w-[18px]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 20h9"/><path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4z"/></svg>
                </button>
            </div>

            <form wire:submit="savePolicy">
                {{-- Password strength --}}
                <h3 class="px-6 pt-3 pb-2 bg-gray-50 dark:bg-gray-700/40 border-b border-gray-100 dark:border-gray-700 text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ __('Password strength') }}</h3>

                <div class="{{ $rowClass }}">
                    <div class="flex-1 basis-72">
                        <label for="password_min_length" class="block text-sm font-medium text-gray-900 dark:text-gray-100">{{ __('Minimum password length') }}</label>
                        <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('Between 6 and 64 characters.') }}</p>
                    </div>
                    <div class="basis-80">
                        @if ($editing)
                            <div class="flex items-center gap-2">
                                <input id="password_min_length" type="number" min="6" max="64" wire:model="password_min_length" class="{{ $inputClass }}">
                                <span class="text-sm text-gray-500 dark:text-gray-400">{{ __('characters') }}</span>
                            </div>
                            @error('password_min_length') <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
                        @else
                            <span class="text-sm font-semibold text-gray-900 dark:text-gray-100">{{ __(':n characters', ['n' => $policy['password_min_length']]) }}</span>
                        @endif
                    </div>
                </div>

                <div class="{{ $rowClass }} !items-start">
                    <div class="flex-1 basis-72">
                        <span class="block text-sm font-medium text-gray-900 dark:text-gray-100">{{ __('Password must contain') }}</span>
                        <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('At least one of each selected type.') }}</p>
                    </div>
                    <div class="basis-80">
                        @php
                            $charRules = [
                                'password_require_uppercase' => [__('Uppercase'), 'A–Z'],
                                'password_require_lowercase' => [__('Lowercase'), 'a–z'],
                                'password_require_number' => [__('Number'), '0–9'],
                                'password_require_symbol' => [__('Symbol'), '!@#$'],
                            ];
                        @endphp
                        @if ($editing)
                            <div class="grid grid-cols-2 gap-2">
                                @foreach ($charRules as $field => [$label, $hint])
                                    <label class="flex min-h-[44px] cursor-pointer items-center gap-2.5 rounded-md border border-gray-300 dark:border-gray-600 px-3 text-sm text-gray-900 dark:text-gray-100">
                                        <input type="checkbox" wire:model="{{ $field }}" class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                                        <span class="flex-1">{{ $label }}</span>
                                        <span class="font-mono text-xs text-gray-500 dark:text-gray-400">{{ $hint }}</span>
                                    </label>
                                @endforeach
                            </div>
                        @else
                            <div class="flex flex-wrap gap-2">
                                @foreach ($charRules as $field => [$label, $hint])
                                    <span @class([
                                        'inline-flex items-center gap-1 rounded-full px-2.5 py-1 text-sm',
                                        'bg-green-100 dark:bg-green-500/10 font-semibold text-green-800 dark:text-green-400' => $policy[$field],
                                        'bg-gray-100 dark:bg-gray-700 font-medium text-gray-600 dark:text-gray-300' => ! $policy[$field],
                                    ])>{{ $policy[$field] ? '✓' : '✕' }} {{ $label }}</span>
                                @endforeach
                            </div>
                        @endif
                    </div>
                </div>

                {{-- Lockout --}}
                <h3 class="px-6 pt-3 pb-2 bg-gray-50 dark:bg-gray-700/40 border-b border-gray-100 dark:border-gray-700 text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ __('Lockout') }}</h3>

                @foreach ([
                    ['lockout_attempts', __('Number of attempts to lockout'), __('Failed logins in a row before the account locks.'), 1, 20, __('attempts')],
                    ['lockout_minutes', __('Number of minutes to lockout'), __('How long a locked account stays locked.'), 1, 1440, __('minutes')],
                ] as [$field, $label, $help, $min, $max, $unit])
                    <div class="{{ $rowClass }}">
                        <div class="flex-1 basis-72">
                            <label for="{{ $field }}" class="block text-sm font-medium text-gray-900 dark:text-gray-100">{{ $label }}</label>
                            <p class="text-sm text-gray-500 dark:text-gray-400">{{ $help }}</p>
                        </div>
                        <div class="basis-80">
                            @if ($editing)
                                <div class="flex items-center gap-2">
                                    <input id="{{ $field }}" type="number" min="{{ $min }}" max="{{ $max }}" wire:model="{{ $field }}" class="{{ $inputClass }}">
                                    <span class="text-sm text-gray-500 dark:text-gray-400">{{ $unit }}</span>
                                </div>
                                @error($field) <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
                            @else
                                <span class="text-sm font-semibold text-gray-900 dark:text-gray-100">{{ $policy[$field] }} {{ $unit }}</span>
                            @endif
                        </div>
                    </div>
                @endforeach

                {{-- History & sessions --}}
                <h3 class="px-6 pt-3 pb-2 bg-gray-50 dark:bg-gray-700/40 border-b border-gray-100 dark:border-gray-700 text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ __('History & sessions') }}</h3>

                <div class="{{ $rowClass }}">
                    <div class="flex-1 basis-72">
                        <label for="password_history" class="block text-sm font-medium text-gray-900 dark:text-gray-100">{{ __('Password history') }}</label>
                        <p class="text-sm text-gray-500 dark:text-gray-400">{{ __("Users can't reuse their last N passwords. 0 turns it off.") }}</p>
                    </div>
                    <div class="basis-80">
                        @if ($editing)
                            <div class="flex items-center gap-2">
                                <input id="password_history" type="number" min="0" max="24" wire:model="password_history" class="{{ $inputClass }}">
                                {{-- Not __('passwords'): that key resolves to the whole lang/en/passwords.php array. --}}
                                <span class="text-sm text-gray-500 dark:text-gray-400">{{ __('previous passwords') }}</span>
                            </div>
                            @error('password_history') <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
                        @else
                            <span class="text-sm font-semibold text-gray-900 dark:text-gray-100">
                                {{ $policy['password_history'] > 0 ? __('Last :n passwords', ['n' => $policy['password_history']]) : __('Off') }}
                            </span>
                        @endif
                    </div>
                </div>

                <div class="{{ $rowClass }}">
                    <div class="flex-1 basis-72">
                        <label for="session_timeout_minutes" class="block text-sm font-medium text-gray-900 dark:text-gray-100">{{ __('Session timeout') }}</label>
                        <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('Idle time before a user is logged out automatically.') }}</p>
                    </div>
                    <div class="basis-80">
                        @if ($editing)
                            <div class="flex items-center gap-2">
                                <input id="session_timeout_minutes" type="number" min="5" max="1440" wire:model="session_timeout_minutes" class="{{ $inputClass }}">
                                <span class="text-sm text-gray-500 dark:text-gray-400">{{ __('minutes') }}</span>
                            </div>
                            @error('session_timeout_minutes') <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
                        @else
                            <span class="text-sm font-semibold text-gray-900 dark:text-gray-100">{{ $policy['session_timeout_minutes'] }} {{ __('minutes') }}</span>
                        @endif
                    </div>
                </div>

                <div class="mx-6 my-4 flex items-start gap-2.5 rounded-md border border-amber-200 dark:border-amber-500/30 bg-amber-50 dark:bg-amber-500/10 px-3.5 py-3 text-sm text-amber-800 dark:text-amber-300">
                    <svg aria-hidden="true" class="mt-0.5 h-4 w-4 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M12 8v5M12 16h.01"/></svg>
                    <span>{{ __("Users whose password doesn't meet these rules can still log in, but must set a new password before they can use the app.") }}</span>
                </div>

                @if ($editing)
                    <div class="flex flex-wrap justify-between gap-3 px-6 py-4 border-t border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-700/40">
                        <button type="button" wire:click="resetToDefaults" class="min-h-[44px] px-4 rounded-md border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-sm font-medium text-gray-700 dark:text-gray-200 hover:bg-gray-50">{{ __('Reset to defaults') }}</button>
                        <div class="flex gap-3">
                            <button type="button" wire:click="cancelEditing" class="min-h-[44px] px-4 rounded-md border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-sm font-medium text-gray-700 dark:text-gray-200 hover:bg-gray-50">{{ __('Cancel') }}</button>
                            <button type="submit" class="min-h-[44px] px-5 rounded-md bg-indigo-600 text-sm font-semibold text-white hover:bg-indigo-500" wire:loading.attr="disabled">{{ __('Save policy') }}</button>
                        </div>
                    </div>
                @endif
            </form>

            <div x-show="saved" x-cloak role="status" class="px-6 py-3 border-t border-green-200 dark:border-green-500/20 bg-green-50 dark:bg-green-500/10 text-sm font-medium text-green-800 dark:text-green-300">
                {{ __('Policy saved.') }}
            </div>
        </section>
    @endif

    {{-- =========================== Locked Accounts =========================== --}}
    @if ($tab === 'locked')
        <section role="tabpanel" class="{{ $cardClass }} overflow-hidden">
            <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100 dark:border-gray-700">
                <h2 class="text-base font-semibold text-gray-900 dark:text-gray-100">{{ __('Locked accounts') }}</h2>
                <span class="rounded-full bg-amber-100 dark:bg-amber-500/10 px-3 py-1 text-sm font-semibold text-amber-800 dark:text-amber-400">{{ __(':n locked', ['n' => $stats['locked_accounts']]) }}</span>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-100 dark:divide-gray-700 text-sm">
                    <thead class="bg-gray-50 dark:bg-gray-700/50 text-xs uppercase text-gray-500 dark:text-gray-400">
                        <tr>
                            <th class="px-4 py-2.5 text-left">{{ __('User') }}</th>
                            <th class="px-4 py-2.5 text-left">{{ __('Role') }}</th>
                            <th class="px-4 py-2.5 text-left">{{ __('Failed attempts') }}</th>
                            <th class="px-4 py-2.5 text-left">{{ __('Unlocks') }}</th>
                            <th class="px-4 py-2.5 text-right">{{ __('Action') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                        @forelse ($this->lockedUsers as $user)
                            <tr wire:key="locked-{{ $user->id }}">
                                <td class="px-4 py-3">
                                    <p class="font-medium text-gray-900 dark:text-gray-100">{{ $user->name }}</p>
                                    <p class="text-gray-500 dark:text-gray-400">{{ $user->email }}</p>
                                </td>
                                <td class="px-4 py-3 text-gray-600 dark:text-gray-300">{{ ucfirst($user->role->value) }}</td>
                                <td class="px-4 py-3 text-gray-600 dark:text-gray-300 tabular-nums">{{ $user->failed_login_attempts }}</td>
                                <td class="px-4 py-3 text-gray-600 dark:text-gray-300" title="{{ $user->locked_until->toDayDateTimeString() }}">{{ $user->locked_until->diffForHumans() }}</td>
                                <td class="px-4 py-3 text-right">
                                    <button type="button" wire:click="unlock({{ $user->id }})" class="min-h-[36px] px-3.5 rounded-md border border-indigo-200 dark:border-indigo-500/30 bg-indigo-50 dark:bg-indigo-500/10 text-sm font-semibold text-indigo-700 dark:text-indigo-300">{{ __('Unlock') }}</button>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="px-4 py-10 text-center text-gray-500 dark:text-gray-400">{{ __('No accounts are locked right now.') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    @endif

    {{-- =========================== Active Sessions =========================== --}}
    @if ($tab === 'sessions')
        <section role="tabpanel" class="{{ $cardClass }} overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-100 dark:border-gray-700">
                <h2 class="text-base font-semibold text-gray-900 dark:text-gray-100">{{ __('Active sessions') }}</h2>
                <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('Signed-in sessions that have not timed out. Online means active in the last 5 minutes.') }}</p>
                @error('sessions') <p class="mt-2 text-sm text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
            </div>

            @if (! $this->usesDatabaseSessions())
                <p class="px-6 py-10 text-center text-sm text-gray-500 dark:text-gray-400">{{ __('Session listing needs SESSION_DRIVER=database.') }}</p>
            @else
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-100 dark:divide-gray-700 text-sm">
                        <thead class="bg-gray-50 dark:bg-gray-700/50 text-xs uppercase text-gray-500 dark:text-gray-400">
                            <tr>
                                <th class="px-4 py-2.5 text-left">{{ __('User') }}</th>
                                <th class="px-4 py-2.5 text-left">{{ __('Device') }}</th>
                                <th class="px-4 py-2.5 text-left">{{ __('IP address') }}</th>
                                <th class="px-4 py-2.5 text-left">{{ __('Last active') }}</th>
                                <th class="px-4 py-2.5 text-left">{{ __('Status') }}</th>
                                <th class="px-4 py-2.5 text-right">{{ __('Actions') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                            @forelse ($this->sessions as $session)
                                <tr wire:key="session-{{ $session['key'] }}">
                                    <td class="px-4 py-3">
                                        <p class="font-medium text-gray-900 dark:text-gray-100">{{ $session['user']->name }}</p>
                                        <p class="text-gray-500 dark:text-gray-400">{{ $session['current'] ? __('This session') : ucfirst($session['user']->role->value) }}</p>
                                    </td>
                                    <td class="px-4 py-3 text-gray-600 dark:text-gray-300 whitespace-nowrap">{{ $session['device'] }}</td>
                                    <td class="px-4 py-3 font-mono text-xs text-gray-600 dark:text-gray-300">{{ $session['ip_address'] ?? '—' }}</td>
                                    <td class="px-4 py-3 text-gray-600 dark:text-gray-300 whitespace-nowrap">{{ $session['last_active']->diffForHumans() }}</td>
                                    <td class="px-4 py-3">
                                        @if ($session['online'])
                                            <span class="inline-flex items-center gap-1.5 text-sm font-semibold text-green-700 dark:text-green-400"><span aria-hidden="true" class="h-2 w-2 rounded-full bg-green-500"></span>{{ __('Online') }}</span>
                                        @else
                                            <span class="inline-flex items-center gap-1.5 text-sm text-gray-500 dark:text-gray-400"><span aria-hidden="true" class="h-2 w-2 rounded-full bg-gray-400"></span>{{ __('Idle') }}</span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3">
                                        @unless ($session['current'])
                                            <div class="flex justify-end gap-2">
                                                <button type="button" wire:click="revokeSession('{{ $session['key'] }}')" wire:confirm="{{ __('Sign :name out of this session?', ['name' => $session['user']->name]) }}" class="min-h-[36px] px-3 rounded-md border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-sm font-medium text-gray-700 dark:text-gray-200">{{ __('Revoke') }}</button>
                                                <button type="button" wire:click="signOutEverywhere({{ $session['user']->id }})" wire:confirm="{{ __('Sign :name out of every session?', ['name' => $session['user']->name]) }}" class="min-h-[36px] px-3 rounded-md border border-red-200 dark:border-red-500/30 bg-red-50 dark:bg-red-500/10 text-sm font-semibold text-red-700 dark:text-red-400">{{ __('Sign out everywhere') }}</button>
                                            </div>
                                        @else
                                            <span class="block text-right text-gray-400">—</span>
                                        @endunless
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="6" class="px-4 py-10 text-center text-gray-500 dark:text-gray-400">{{ __('No active sessions.') }}</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            @endif
        </section>
    @endif

    {{-- =========================== Security Events =========================== --}}
    @if ($tab === 'events')
        <section role="tabpanel" class="{{ $cardClass }} overflow-hidden">
            <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100 dark:border-gray-700">
                <h2 class="text-base font-semibold text-gray-900 dark:text-gray-100">{{ __('Security events') }}</h2>
                <a href="{{ route('audit-logs.index', ['module' => 'security']) }}" wire:navigate class="text-sm font-medium text-indigo-600 dark:text-indigo-400 hover:underline">{{ __('Open in Audit Log') }} →</a>
            </div>
            @include('livewire.partials.security-event-list', ['events' => $events, 'badgeFor' => fn ($action) => $this->eventBadge($action)])
            @if ($events->hasPages())
                <div class="px-6 py-3 border-t border-gray-100 dark:border-gray-700">{{ $events->links() }}</div>
            @endif
        </section>
    @endif
</div>
