@php
    $filters = ['all' => __('All'), 'unread' => __('Unread'), 'read' => __('Read')];
    $typeStyle = [
        'member_created' => ['bg-indigo-50 text-indigo-600 dark:bg-indigo-500/10 dark:text-indigo-300', 'members', __('New member')],
        'membership_renewed' => ['bg-green-50 text-green-700 dark:bg-green-500/10 dark:text-green-300', 'check-circle', __('Renewal')],
        'membership_expired' => ['bg-amber-50 text-amber-700 dark:bg-amber-500/10 dark:text-amber-300', 'clock', __('Expired')],
    ];
@endphp

<div class="max-w-3xl space-y-5">
    <div class="flex flex-wrap items-end justify-between gap-3">
        <div>
            <h1 class="text-xl font-semibold text-gray-900 dark:text-gray-100">{{ __('Notifications') }}</h1>
            <p class="text-sm text-gray-500 dark:text-gray-400">
                {{ trans_choice('{0} You\'re all caught up.|{1} :count unread notification.|[2,*] :count unread notifications.', $this->unreadCount) }}
            </p>
        </div>
        @if ($this->unreadCount > 0)
            <button type="button" wire:click="markAllRead" class="min-h-[40px] px-4 rounded-md border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-sm font-medium text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-700">
                {{ __('Mark all as read') }}
            </button>
        @endif
    </div>

    <div role="group" aria-label="{{ __('Show') }}" class="inline-flex gap-0.5 rounded-lg bg-gray-100 dark:bg-gray-700/60 p-1">
        @foreach ($filters as $key => $label)
            <button
                type="button"
                wire:click="setFilter('{{ $key }}')"
                aria-pressed="{{ $filter === $key ? 'true' : 'false' }}"
                @class([
                    'min-h-[34px] rounded-md px-4 text-sm transition-colors',
                    'bg-white dark:bg-gray-800 font-semibold text-indigo-700 dark:text-indigo-300 shadow-sm' => $filter === $key,
                    'font-medium text-gray-600 dark:text-gray-300 hover:text-gray-900 dark:hover:text-white' => $filter !== $key,
                ])
            >
                {{ $label }}
                @if ($key === 'unread' && $this->unreadCount > 0)
                    <span class="ml-1 rounded-full bg-red-500 px-1.5 text-xs font-semibold text-white">{{ $this->unreadCount }}</span>
                @endif
            </button>
        @endforeach
    </div>

    <ul class="overflow-hidden rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 divide-y divide-gray-100 dark:divide-gray-700">
        @forelse ($this->notifications as $notification)
            @php [$tile, $icon, $typeLabel] = $typeStyle[$notification->type->value] ?? ['bg-gray-100 text-gray-600', 'clock', '']; @endphp
            <li wire:key="n-{{ $notification->id }}" @class([
                'flex items-start gap-3 px-4 sm:px-5 py-3.5',
                'bg-indigo-50/40 dark:bg-indigo-500/5' => ! $notification->read_status,
            ])>
                <span aria-hidden="true" class="mt-0.5 flex h-8 w-8 shrink-0 items-center justify-center rounded-lg {{ $tile }}">
                    <x-icon :name="$icon" class="h-4 w-4" />
                </span>
                <div class="min-w-0 flex-1">
                    <p @class(['text-sm', 'font-medium text-gray-900 dark:text-gray-100' => ! $notification->read_status, 'text-gray-600 dark:text-gray-300' => $notification->read_status])>
                        {{ $notification->message }}
                    </p>
                    <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">
                        {{ $typeLabel }} · <span title="{{ $notification->created_at->toDayDateTimeString() }}">{{ $notification->created_at->diffForHumans() }}</span>
                        @unless ($notification->read_status)
                            · <span class="font-semibold text-indigo-600 dark:text-indigo-400">{{ __('Unread') }}</span>
                        @endunless
                    </p>
                </div>
                @unless ($notification->read_status)
                    <button type="button" wire:click="markAsRead({{ $notification->id }})" title="{{ __('Mark as read') }}" aria-label="{{ __('Mark as read') }}"
                            class="shrink-0 flex h-9 w-9 items-center justify-center rounded-md text-gray-400 hover:bg-gray-100 hover:text-indigo-600 dark:hover:bg-gray-700 dark:hover:text-indigo-400">
                        <svg aria-hidden="true" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" /></svg>
                    </button>
                @endunless
            </li>
        @empty
            <li class="px-5 py-12 text-center text-sm text-gray-500 dark:text-gray-400">
                {{ $filter === 'unread' ? __('No unread notifications.') : __('No notifications yet.') }}
            </li>
        @endforelse
    </ul>

    {{ $this->notifications->links() }}
</div>
