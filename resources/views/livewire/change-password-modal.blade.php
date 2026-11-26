<div>
    <x-modal name="change-password" maxWidth="md" focusable>
        <form wire:submit="save" class="p-6 space-y-4 dark:bg-gray-800">
            <div>
                <h2 class="text-lg font-semibold text-gray-900 dark:text-gray-100">
                    {{ $isSelf ? __('Change your password') : __('Change password for :name', ['name' => $userName]) }}
                </h2>
                @unless ($isSelf)
                    <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                        {{ __('They will be asked to choose their own new password the next time they log in.') }}
                    </p>
                @endunless
            </div>

            <div>
                <label for="cp-password" class="block text-sm font-medium text-gray-700 dark:text-gray-200">{{ __('New password') }}</label>
                <input id="cp-password" type="password" wire:model="password" autocomplete="new-password"
                       aria-describedby="cp-requirements @error('password') cp-password-error @enderror"
                       class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                @error('password')
                    <p id="cp-password-error" class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                @enderror
                <x-password-requirements id="cp-requirements" value="$wire.password" class="mt-2" />
            </div>

            <div>
                <label for="cp-password-confirmation" class="block text-sm font-medium text-gray-700 dark:text-gray-200">{{ __('Confirm password') }}</label>
                <input id="cp-password-confirmation" type="password" wire:model="password_confirmation" autocomplete="new-password"
                       class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                {{-- Instant feedback while typing; the server's "confirmed" rule is still the real check. --}}
                <p x-show="$wire.password_confirmation && $wire.password !== $wire.password_confirmation" x-cloak
                   class="mt-1 text-sm text-red-600 dark:text-red-400">{{ __("The passwords don't match.") }}</p>
            </div>

            <div class="flex justify-end gap-3 pt-2">
                <button type="button" x-on:click="$dispatch('close-modal', 'change-password')"
                        class="min-h-[40px] px-4 rounded-md border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-sm font-medium text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-700">
                    {{ __('Cancel') }}
                </button>
                <button type="submit" wire:loading.attr="disabled"
                        class="min-h-[40px] px-4 rounded-md bg-indigo-600 text-sm font-semibold text-white hover:bg-indigo-500 disabled:opacity-60">
                    {{ __('Change password') }}
                </button>
            </div>
        </form>
    </x-modal>
</div>
