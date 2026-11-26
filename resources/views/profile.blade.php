<x-app-layout>
    <div class="max-w-3xl space-y-6">
        <div>
            <h1 class="text-xl font-semibold text-gray-900 dark:text-gray-100">{{ __('Profile') }}</h1>
            <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('Your account details and password.') }}</p>
        </div>

        <livewire:profile-details />

        {{-- No self-service "Delete account": deactivating staff is
             Admin-only (§3.2), done under Users, where UserPolicy also
             stops the last Admin from being removed. --}}
    </div>

    <livewire:change-password-modal />
</x-app-layout>
