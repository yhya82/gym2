<div class="max-w-2xl space-y-6">
    <div>
        <h1 class="text-xl font-semibold text-gray-900 dark:text-gray-100">{{ __('Settings') }}</h1>
        <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('Branding, contact information, and regional preferences.') }}</p>
    </div>

    <div
        x-data="{ show: false }"
        x-on:settings-saved.window="show = true; setTimeout(() => show = false, 3000)"
        x-show="show"
        x-cloak
        class="rounded-md bg-green-50 dark:bg-green-500/10 border border-green-200 dark:border-green-500/20 px-4 py-3 text-sm text-green-800 dark:text-green-300"
    >
        {{ __('Settings updated successfully.') }}
    </div>

    <form wire:submit="save" class="space-y-6">
        <div class="rounded-lg border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 p-5 space-y-4">
            <h2 class="text-sm font-medium text-gray-700 dark:text-gray-200">{{ __('General') }}</h2>

            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-200">{{ __('Application Name') }} *</label>
                <input type="text" wire:model="application_name" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                @error('application_name') <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
            </div>

            <div class="grid sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-200">{{ __('Location') }}</label>
                    <input type="text" wire:model="location" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-200">{{ __('Phone') }}</label>
                    <input type="text" wire:model="phone" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                </div>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-200">{{ __('Email') }}</label>
                <input type="email" wire:model="email" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                @error('email') <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
            </div>
        </div>

        <div class="rounded-lg border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 p-5 space-y-4">
            <h2 class="text-sm font-medium text-gray-700 dark:text-gray-200">{{ __('Regional') }}</h2>

            <div class="grid sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-200">{{ __('Currency') }} *</label>
                    <input type="text" wire:model="currency" maxlength="3" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 uppercase">
                    @error('currency') <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-200">{{ __('Timezone') }} *</label>
                    <input type="text" wire:model="timezone" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    @error('timezone') <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
                </div>
            </div>
        </div>

        <div class="rounded-lg border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 p-5 space-y-4">
            <h2 class="text-sm font-medium text-gray-700 dark:text-gray-200">{{ __('Appearance') }}</h2>

            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-200">{{ __('Default Theme') }}</label>
                <select wire:model="default_theme" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    <option value="light">{{ __('Light') }}</option>
                    <option value="dark">{{ __('Dark') }}</option>
                </select>
            </div>
        </div>

        <div class="flex justify-end">
            <button type="submit" wire:loading.attr="disabled" wire:target="save" class="inline-flex items-center px-4 py-2 rounded-md bg-indigo-600 text-white text-sm font-medium hover:bg-indigo-500 disabled:opacity-50">
                {{ __('Save Settings') }}
            </button>
        </div>
    </form>
</div>
