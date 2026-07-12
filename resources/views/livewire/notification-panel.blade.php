<div class="relative" x-data="{ open: false }" @click.outside="open = false">
    <button @click="open = ! open; if (open) { $wire.markAllRead() }" class="relative p-2 rounded-md text-gray-500 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-700" aria-label="Notifications">
        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" />
        </svg>

        @if ($this->unreadCount > 0)
            <span class="absolute -top-0.5 -right-0.5 inline-flex items-center justify-center h-4 min-w-4 px-1 rounded-full bg-red-500 text-white text-[10px] font-semibold leading-none">
                {{ $this->unreadCount > 9 ? '9+' : $this->unreadCount }}
            </span>
        @endif
    </button>

    <div
        x-show="open"
        x-cloak
        x-transition:enter="transition ease-out duration-150"
        x-transition:enter-start="opacity-0 scale-95"
        x-transition:enter-end="opacity-100 scale-100"
        class="absolute right-0 z-50 mt-2 w-80 rounded-md shadow-lg bg-white dark:bg-gray-800 ring-1 ring-black/5 dark:ring-white/10"
        style="display: none;"
    >
        <div class="px-4 py-3 border-b border-gray-100 dark:border-gray-700 font-medium text-sm text-gray-700 dark:text-gray-200">
            {{ __('Notifications') }}
        </div>

        <div class="max-h-80 overflow-y-auto divide-y divide-gray-100 dark:divide-gray-700">
            @forelse ($this->notifications as $notification)
                <div class="px-4 py-3 text-sm {{ $notification->read_status ? 'text-gray-500 dark:text-gray-400' : 'text-gray-800 dark:text-gray-100 bg-indigo-50/50 dark:bg-indigo-500/5' }}">
                    <p>{{ $notification->message }}</p>
                    <p class="mt-1 text-xs text-gray-400 dark:text-gray-500">{{ $notification->created_at->diffForHumans() }}</p>
                </div>
            @empty
                <p class="px-4 py-6 text-sm text-center text-gray-400 dark:text-gray-500">{{ __('No notifications yet.') }}</p>
            @endforelse
        </div>
    </div>
</div>
