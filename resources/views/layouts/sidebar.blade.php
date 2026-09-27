@php
$settings = \App\Models\ApplicationSetting::current();
$isAdmin = auth()->user()->role === \App\Enums\UserRole::Admin;
$logoUrl = \App\Models\ApplicationSetting::urlFor($settings->logo);
@endphp

<aside
    x-data="{ mobileOpen: false }"
    class="shrink-0"
>
    <!-- Mobile toggle -->
    <div class="lg:hidden fixed top-0 inset-x-0 z-30 h-16 flex items-center px-4 bg-white dark:bg-gray-800 border-b border-gray-200 dark:border-gray-700">
        <button @click.stop="mobileOpen = true" class="p-2 -ml-2 text-gray-500 dark:text-gray-400">
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
        :class="[mobileOpen ? 'translate-x-0' : '-translate-x-full lg:translate-x-0', $store.sidebar.collapsed ? 'lg:w-16' : 'lg:w-64']"
        class="fixed lg:relative inset-y-0 left-0 z-50 w-64 h-full bg-white dark:bg-gray-800 border-r border-gray-200 dark:border-gray-700 flex flex-col transition-all duration-200 ease-in-out"
    >
        <div :class="$store.sidebar.collapsed ? 'lg:px-3' : ''" class="h-16 shrink-0 flex items-center gap-2 px-6 border-b border-gray-200 dark:border-gray-700">
           @if ($logoUrl)
                <img src="{{ $logoUrl }}"
                alt=""class="h-8 w-8 rounded object-cover shrink-0">
            @else
             <x-application-logo class="h-8 w-8 fill-current text-indigo-600" />
            @endif

             <span :class="$store.sidebar.collapsed ? 'lg:hidden' : ''" class="font-semibold text-gray-800 dark:text-gray-100 truncate">
                {{ $settings->application_name }}
            </span>
        </div>

        <div class="flex-1 overflow-y-auto py-4 px-3 space-y-1">
            <x-sidebar-link :href="route('dashboard')" :active="request()->routeIs('dashboard')" title="{{ __('Dashboard') }}" wire:navigate>
                <x-icon name="dashboard" class="h-5 w-5 shrink-0" />
                <span x-bind:class="$store.sidebar.collapsed ? 'lg:hidden' : ''">{{ __('Dashboard') }}</span>
            </x-sidebar-link>

            <x-sidebar-link :href="route('members.index')" :active="request()->routeIs('members.*')" title="{{ __('Members') }}" wire:navigate>
                <x-icon name="members" class="h-5 w-5 shrink-0" />
                <span x-bind:class="$store.sidebar.collapsed ? 'lg:hidden' : ''">{{ __('Members') }}</span>
            </x-sidebar-link>

            @if ($isAdmin)
                <x-sidebar-link :href="route('plans.index')" :active="request()->routeIs('plans.*')" title="{{ __('Plans') }}" wire:navigate>
                    <x-icon name="plans" class="h-5 w-5 shrink-0" />
                    <span x-bind:class="$store.sidebar.collapsed ? 'lg:hidden' : ''">{{ __('Plans') }}</span>
                </x-sidebar-link>

                <x-sidebar-link :href="route('payments.index')" :active="request()->routeIs('payments.*')" title="{{ __('Payments') }}" wire:navigate>
                    <x-icon name="payments" class="h-5 w-5 shrink-0" />
                    <span x-bind:class="$store.sidebar.collapsed ? 'lg:hidden' : ''">{{ __('Payments') }}</span>
                </x-sidebar-link>

                <x-sidebar-link :href="route('expenses.index')" :active="request()->routeIs('expenses.*')" title="{{ __('Expenses') }}" wire:navigate>
                    <x-icon name="expenses" class="h-5 w-5 shrink-0" />
                    <span x-bind:class="$store.sidebar.collapsed ? 'lg:hidden' : ''">{{ __('Expenses') }}</span>
                </x-sidebar-link>

                <x-sidebar-link :href="route('users.index')" :active="request()->routeIs('users.*')" title="{{ __('Users') }}" wire:navigate>
                    <x-icon name="users" class="h-5 w-5 shrink-0" />
                    <span x-bind:class="$store.sidebar.collapsed ? 'lg:hidden' : ''">{{ __('Users') }}</span>
                </x-sidebar-link>

                <x-sidebar-link :href="route('audit-logs.index')" :active="request()->routeIs('audit-logs.*')" title="{{ __('Audit Log') }}" wire:navigate>
                    <x-icon name="audit" class="h-5 w-5 shrink-0" />
                    <span x-bind:class="$store.sidebar.collapsed ? 'lg:hidden' : ''">{{ __('Audit Log') }}</span>
                </x-sidebar-link>

                <x-sidebar-link :href="route('settings.show')" :active="request()->routeIs('settings.*')" title="{{ __('Settings') }}" wire:navigate>
                    <x-icon name="settings" class="h-5 w-5 shrink-0" />
                    <span x-bind:class="$store.sidebar.collapsed ? 'lg:hidden' : ''">{{ __('Settings') }}</span>
                </x-sidebar-link>
            @endif
        </div>
    </nav>
</aside>
