<div>
    <x-modal name="renewal-modal" focusable>
        <div class="p-6 bg-white dark:bg-gray-800">
            <h2 class="text-lg font-medium text-gray-900 dark:text-gray-100">{{ __('Renew Membership') }}</h2>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                {{ $memberName }} —
                <span class="{{ $memberStatus === 'active' ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400' }}">
                    {{ ucfirst($memberStatus) }}
                </span>
            </p>

            <form wire:submit="renew" class="mt-4 space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-200">{{ __('Plan') }} *</label>
                    <select wire:model.live="plan_id" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                        <option value="">{{ __('Select a plan…') }}</option>
                        @foreach ($this->plans as $plan)
                            <option value="{{ $plan->id }}">{{ $plan->plan_name }} — {{ $plan->duration_days }} {{ __('days') }}, {{ number_format($plan->price, 2) }}</option>
                        @endforeach
                    </select>
                    @error('plan_id') <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-200">{{ __('Start Date') }} *</label>
                    <input type="date" wire:model.live="start_date" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    @error('start_date') <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-200">{{ __('Payment Amount') }} *</label>
                    <input type="number" step="0.01" wire:model="payment_amount" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    @error('payment_amount') <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
                </div>

                @if ($this->newExpiryDate)
                    <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('New expiry date:') }} <span class="font-medium text-gray-800 dark:text-gray-200">{{ $this->newExpiryDate }}</span></p>
                @endif

                <div class="flex items-center justify-end gap-3 pt-2">
                    <button type="button" x-on:click="$dispatch('close-modal', 'renewal-modal')" class="px-4 py-2 rounded-md border border-gray-300 dark:border-gray-600 text-sm font-medium text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-700">
                        {{ __('Cancel') }}
                    </button>
                    <button type="submit" wire:loading.attr="disabled" wire:target="renew" class="inline-flex items-center px-4 py-2 rounded-md bg-indigo-600 text-white text-sm font-medium hover:bg-indigo-500 disabled:opacity-50">
                        <span wire:loading.remove wire:target="renew">{{ __('Renew') }}</span>
                        <span wire:loading wire:target="renew">{{ __('Renewing…') }}</span>
                    </button>
                </div>
            </form>
        </div>
    </x-modal>
</div>
