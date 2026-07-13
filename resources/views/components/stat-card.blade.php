@props(['label', 'value', 'icon' => null, 'accent' => 'indigo'])

@php
$accents = [
    'indigo' => 'bg-indigo-50 dark:bg-indigo-500/10 text-indigo-600 dark:text-indigo-400',
    'green' => 'bg-green-50 dark:bg-green-500/10 text-green-600 dark:text-green-400',
    'red' => 'bg-red-50 dark:bg-red-500/10 text-red-600 dark:text-red-400',
];
$accentClasses = $accents[$accent] ?? $accents['indigo'];
@endphp

<div {{ $attributes->merge(['class' => 'rounded-lg border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 px-5 py-4 flex items-center gap-4']) }}>
    @if ($icon)
        <div class="shrink-0 h-10 w-10 rounded-md flex items-center justify-center {{ $accentClasses }}">
            <x-icon :name="$icon" class="h-5 w-5" />
        </div>
    @endif
    <div class="min-w-0">
        <p class="text-sm text-gray-500 dark:text-gray-400">{{ $label }}</p>
        <p class="mt-1 text-2xl font-semibold text-gray-900 dark:text-gray-100 tabular-nums">{{ $value }}</p>
    </div>
</div>
