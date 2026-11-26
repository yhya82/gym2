<div {{ $attributes->merge(['class' => 'rounded-md border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-700/40 px-3 py-2.5']) }}>
    <p class="text-xs font-medium text-gray-600 dark:text-gray-300">{{ __('Password must have:') }}</p>
    <ul class="mt-1.5 space-y-1 text-sm">
        @foreach ($rules as $rule)
            <li class="flex items-center gap-2" x-bind:class="({{ $rule['test'] }}) ? 'text-green-700 dark:text-green-400' : 'text-gray-600 dark:text-gray-400'">
                <svg aria-hidden="true" class="h-4 w-4 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.25" stroke-linecap="round" stroke-linejoin="round">
                    <path x-show="{{ $rule['test'] }}" d="M5 12.5l4.5 4.5L19 7.5" />
                    <circle x-show="! ({{ $rule['test'] }})" cx="12" cy="12" r="7.5" />
                </svg>
                <span>{{ $rule['label'] }}</span>
                <span class="sr-only" x-text="({{ $rule['test'] }}) ? @js(__('met')) : @js(__('not met yet'))"></span>
            </li>
        @endforeach
        @if ($history > 0)
            <li class="flex items-center gap-2 text-gray-600 dark:text-gray-400">
                <svg aria-hidden="true" class="h-4 w-4 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg>
                <span>{{ trans_choice('Different from the current password|Not one of the last :count passwords', $history) }} <span class="text-xs">({{ __('checked when you save') }})</span></span>
            </li>
        @endif
    </ul>
</div>
