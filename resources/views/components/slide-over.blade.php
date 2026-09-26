@props(['title', 'name' => null, 'show' => 'showCreate', 'close' => 'showCreate = false'])

@if ($name)
    {{--
        Self-contained mode: owns its own Alpine state, opened/closed via
        window events keyed by `name` — the same open-modal/close-modal
        contract <x-modal> uses. For when the trigger button lives in a
        different Livewire component than the panel itself (e.g. Member's
        shared create/edit form, triggered from the index page's row).
    --}}
    <div
        x-data="{ show: false }"
        x-on:open-modal.window="$event.detail == '{{ $name }}' ? show = true : null"
        x-on:close-modal.window="$event.detail == '{{ $name }}' ? show = false : null"
        x-on:keydown.escape.window="show = false"
        x-show="show"
        x-cloak
        class="fixed inset-0 z-40"
    >
        <div
            class="absolute inset-0 bg-black/40"
            x-on:click="show = false"
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
                <button type="button" @click="show = false" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-200">
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
@else
    {{--
        Driven by the enclosing x-data scope — a plain Blade component doesn't
        open its own Alpine scope, so `show`/`close` are raw Alpine expressions
        evaluated against whatever the parent already declares. Defaults match
        the original single-panel usage (bound to `showCreate`); a repeated
        per-row panel (e.g. one edit slide-over per table row) passes its own
        expressions instead, such as show="editing === 5" close="editing = null".
    --}}
    <div x-show="{{ $show }}" x-cloak class="fixed inset-0 z-40" x-on:keydown.escape.window="{{ $close }}">
        <div
            class="absolute inset-0 bg-black/40"
            x-on:click="{{ $close }}"
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
                <button type="button" @click="{{ $close }}" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-200">
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
@endif
