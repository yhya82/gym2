@props(['title'])

{{--
    Driven by the enclosing x-data scope's `showCreate` — a plain Blade
    component doesn't open its own Alpine scope, so this binds directly to
    whichever "showCreate" the parent already declares, same as the
    inline panel it replaces did.
--}}
<div x-show="showCreate" x-cloak class="fixed inset-0 z-40" x-on:keydown.escape.window="showCreate = false">
    <div
        class="absolute inset-0 bg-black/40"
        x-on:click="showCreate = false"
        x-transition:enter="ease-out duration-300"
        x-transition:enter-start="opacity-0"
        x-transition:enter-end="opacity-100"
        x-transition:leave="ease-in duration-200"
        x-transition:leave-start="opacity-100"
        x-transition:leave-end="opacity-0"
    ></div>

    <div
        class="absolute inset-y-0 right-0 w-full max-w-md bg-white dark:bg-gray-800 shadow-xl flex flex-col"
        x-transition:enter="ease-out duration-300"
        x-transition:enter-start="translate-x-full"
        x-transition:enter-end="translate-x-0"
        x-transition:leave="ease-in duration-200"
        x-transition:leave-start="translate-x-0"
        x-transition:leave-end="translate-x-full"
    >
        <div class="flex items-center justify-between px-5 py-4 border-b border-gray-200 dark:border-gray-700">
            <h3 class="text-sm font-medium text-gray-700 dark:text-gray-200">{{ $title }}</h3>
            <button type="button" @click="showCreate = false" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-200">
                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                </svg>
            </button>
        </div>
        <div class="flex-1 overflow-y-auto p-5">
            {{ $slot }}
        </div>
    </div>
</div>
