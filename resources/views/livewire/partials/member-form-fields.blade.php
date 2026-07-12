<form wire:submit="save" class="space-y-4">
    <div>
        <label class="block text-sm font-medium text-gray-700 dark:text-gray-200">{{ __('Name') }} *</label>
        <input type="text" wire:model="full_name" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
        @error('full_name') <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
    </div>

    <div>
        <label class="block text-sm font-medium text-gray-700 dark:text-gray-200">{{ __('Phone Number') }} *</label>
        <input type="text" wire:model="phone_number" placeholder="7771234" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
        @error('phone_number') <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
    </div>

    @unless ($memberId)
        <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-200">{{ __('Plan') }} *</label>
            <select wire:model="plan_id" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                <option value="">{{ __('Select a plan…') }}</option>
                @foreach ($this->plans as $plan)
                    <option value="{{ $plan->id }}">{{ $plan->plan_name }} — {{ $plan->duration_days }} {{ __('days') }}, {{ number_format($plan->price, 2) }}</option>
                @endforeach
            </select>
            @error('plan_id') <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-200">{{ __('Start Date') }} *</label>
            <input type="date" wire:model="start_date" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
            @error('start_date') <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-200">{{ __('Payment Amount') }} *</label>
            <input type="number" step="0.01" wire:model="payment_amount" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
            @error('payment_amount') <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
        </div>
    @endunless

    <div class="flex items-center justify-end gap-3 pt-2">
        @unless ($standalone)
            <button type="button" x-on:click="$dispatch('close-modal', 'member-form-modal')" class="px-4 py-2 rounded-md border border-gray-300 dark:border-gray-600 text-sm font-medium text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-700">
                {{ __('Cancel') }}
            </button>
        @endunless

        <button type="submit" wire:loading.attr="disabled" wire:target="save" class="inline-flex items-center px-4 py-2 rounded-md bg-indigo-600 text-white text-sm font-medium hover:bg-indigo-500 disabled:opacity-50">
            <span wire:loading.remove wire:target="save">{{ $memberId ? __('Save Changes') : __('Create Member') }}</span>
            <span wire:loading wire:target="save">{{ __('Saving…') }}</span>
        </button>
    </div>
</form>
