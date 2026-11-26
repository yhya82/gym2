@props(['active' => false])

@php
// Collapsed (desktop): icon only, centred — the label span is hidden by
// lg:sidebar-collapsed:hidden in layouts/sidebar.blade.php.
$base = 'flex items-center gap-3 lg:sidebar-collapsed:justify-center lg:sidebar-collapsed:gap-0 px-3 py-2 rounded-md text-sm font-medium';
$classes = ($active ?? false)
            ? $base.' bg-indigo-50 dark:bg-indigo-500/10 text-indigo-700 dark:text-indigo-300'
            : $base.' text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700/50 hover:text-gray-900 dark:hover:text-white';
@endphp

<a {{ $attributes->merge(['class' => $classes]) }}>
    {{ $slot }}
</a>
