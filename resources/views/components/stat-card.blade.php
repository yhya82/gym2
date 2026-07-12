@props(['label', 'value'])

<div {{ $attributes->merge(['class' => 'rounded-lg border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 px-5 py-4']) }}>
    <p class="text-sm text-gray-500 dark:text-gray-400">{{ $label }}</p>
    <p class="mt-1 text-2xl font-semibold text-gray-900 dark:text-gray-100 tabular-nums">{{ $value }}</p>
</div>
