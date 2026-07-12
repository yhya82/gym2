@php $currency = \App\Models\ApplicationSetting::current()->currency; @endphp

<x-app-layout>
    <div class="space-y-4">
        <h1 class="text-xl font-semibold text-gray-900 dark:text-gray-100">{{ __('Payments') }}</h1>

        <form method="GET" action="{{ route('payments.index') }}">
            <input type="text" name="search" value="{{ request('search') }}" placeholder="{{ __('Search by member name…') }}" onchange="this.form.submit()" class="w-full max-w-sm rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-100 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
        </form>

        <div class="overflow-x-auto rounded-lg border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800">
            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700 text-sm">
                <thead class="bg-gray-50 dark:bg-gray-700/50">
                    <tr class="text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                        <th class="px-4 py-3">{{ __('Date') }}</th>
                        <th class="px-4 py-3">{{ __('Member') }}</th>
                        <th class="px-4 py-3">{{ __('Amount') }}</th>
                        <th class="px-4 py-3">{{ __('Received By') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                    @forelse ($payments as $payment)
                        <tr>
                            <td class="px-4 py-3 text-gray-500 dark:text-gray-400">{{ $payment->payment_date->format('M j, Y') }}</td>
                            <td class="px-4 py-3 font-medium text-gray-900 dark:text-gray-100">
                                <a href="{{ route('members.show', $payment->member_id) }}" wire:navigate class="hover:text-indigo-600 dark:hover:text-indigo-400">
                                    {{ $payment->member->full_name }}
                                </a>
                            </td>
                            <td class="px-4 py-3 text-gray-800 dark:text-gray-100 tabular-nums">{{ $currency }} {{ number_format($payment->amount, 2) }}</td>
                            <td class="px-4 py-3 text-gray-500 dark:text-gray-400">{{ $payment->receivedBy->name }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="px-4 py-10 text-center text-gray-400 dark:text-gray-500">{{ __('No payments found.') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{ $payments->links() }}
    </div>
</x-app-layout>
