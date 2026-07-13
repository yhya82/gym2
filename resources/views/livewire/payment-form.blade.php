@php $currency = \App\Models\ApplicationSetting::current()->currency; @endphp

<div class="rounded-lg border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 p-5">
    <h2 class="text-sm font-medium text-gray-700 dark:text-gray-200 mb-4">{{ __('Record Payment') }}</h2>

    @if ($this->subscription)
        <p class="text-sm text-gray-500 dark:text-gray-400 mb-4">
            {{ __('Remaining balance:') }}
            <span class="font-semibold text-gray-800 dark:text-gray-100 tabular-nums">{{ $currency }} {{ number_format($this->subscription->balance, 2) }}</span>
        </p>

        @if ($this->subscription->status !== \App\Enums\MembershipStatus::Active)
            <p class="text-sm text-amber-600 dark:text-amber-400">{{ __('This membership has expired — renew it instead of recording a payment.') }}</p>
        @elseif ((float) $this->subscription->balance > 0)
            <form wire:submit="record" class="flex items-end gap-3">
                <div class="flex-1">
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-200">{{ __('Amount') }}</label>
                    <input type="number" step="0.01" wire:model="amount" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    @error('amount') <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
                </div>
                <button type="submit" wire:loading.attr="disabled" wire:target="record" class="inline-flex items-center px-4 py-2 rounded-md bg-indigo-600 text-white text-sm font-medium hover:bg-indigo-500 disabled:opacity-50">
                    {{ __('Record Payment') }}
                </button>
            </form>
        @else
            <p class="text-sm text-green-600 dark:text-green-400">{{ __('Fully paid.') }}</p>
        @endif
    @else
        <p class="text-sm text-gray-400 dark:text-gray-500">{{ __('No subscription to record a payment against.') }}</p>
    @endif
</div>
