@php
$settings = \App\Models\ApplicationSetting::current();
$isAdmin = auth()->user()->role === \App\Enums\UserRole::Admin;
@endphp

<aside
    x-data="{ mobileOpen: false }"
    class="shrink-0"
>
    <!-- Mobile toggle -->
    <div class="lg:hidden fixed top-0 inset-x-0 z-30 h-16 flex items-center px-4 bg-white dark:bg-gray-800 border-b border-gray-200 dark:border-gray-700">
        <button @click="mobileOpen = true" class="p-2 -ml-2 text-gray-500 dark:text-gray-400">
            <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
            </svg>
        </button>
        <span class="ml-3 font-semibold truncate">{{ $settings->application_name }}</span>
    </div>

    <div
        x-show="mobileOpen"
        x-cloak
        @click="mobileOpen = false"
        class="lg:hidden fixed inset-0 z-40 bg-black/40"
        style="display: none;"
    ></div>

    <nav
        @click.outside="mobileOpen = false"
        :class="mobileOpen ? 'translate-x-0' : '-translate-x-full lg:translate-x-0'"
        class="fixed lg:static inset-y-0 left-0 z-50 w-64 h-full bg-white dark:bg-gray-800 border-r border-gray-200 dark:border-gray-700 flex flex-col transition-transform duration-200 ease-in-out"
    >
        <div class="h-16 shrink-0 flex items-center gap-2 px-6 border-b border-gray-200 dark:border-gray-700">
            <x-application-logo class="h-8 w-8 fill-current text-indigo-600" />
            <span class="font-semibold text-gray-800 dark:text-gray-100 truncate">{{ $settings->application_name }}</span>
        </div>

        <div class="flex-1 overflow-y-auto py-4 px-3 space-y-1">
            <x-sidebar-link :href="route('dashboard')" :active="request()->routeIs('dashboard')" wire:navigate>
                {{ __('Dashboard') }}
            </x-sidebar-link>

            <x-sidebar-link :href="route('members.index')" :active="request()->routeIs('members.*')" wire:navigate>
                {{ __('Members') }}
            </x-sidebar-link>

            @if ($isAdmin)
                <x-sidebar-link :href="route('plans.index')" :active="request()->routeIs('plans.*')" wire:navigate>
                    {{ __('Plans') }}
                </x-sidebar-link>

                <x-sidebar-link :href="route('payments.index')" :active="request()->routeIs('payments.*')" wire:navigate>
                    {{ __('Payments') }}
                </x-sidebar-link>

                <x-sidebar-link :href="route('users.index')" :active="request()->routeIs('users.*')" wire:navigate>
                    {{ __('Users') }}
                </x-sidebar-link>

                <x-sidebar-link :href="route('settings.show')" :active="request()->routeIs('settings.*')" wire:navigate>
                    {{ __('Settings') }}
                </x-sidebar-link>
            @endif
        </div>
    </nav>
</aside>
