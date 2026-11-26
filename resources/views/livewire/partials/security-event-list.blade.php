<ul>
    @forelse ($events as $event)
        @php [$badge, $badgeClass] = $badgeFor($event->action); @endphp
        <li wire:key="event-{{ $event->id }}" class="flex flex-wrap items-center gap-x-4 gap-y-1 px-6 py-3.5 border-b border-gray-100 dark:border-gray-700 last:border-0">
            <span class="min-w-[96px] rounded px-2 py-0.5 text-center text-xs font-semibold {{ $badgeClass }}">{{ __($badge) }}</span>
            <span class="flex-1 text-sm text-gray-700 dark:text-gray-200">{{ $event->description }}</span>
            <span class="whitespace-nowrap text-sm text-gray-500 dark:text-gray-400" title="{{ $event->created_at->toDayDateTimeString() }}">{{ $event->created_at->diffForHumans() }}</span>
        </li>
    @empty
        <li class="px-6 py-10 text-center text-sm text-gray-500 dark:text-gray-400">{{ __('No security events yet.') }}</li>
    @endforelse
</ul>
