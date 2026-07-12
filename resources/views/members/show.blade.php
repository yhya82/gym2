@php
    $isAdmin = auth()->user()->role === \App\Enums\UserRole::Admin;
    $currency = \App\Models\ApplicationSetting::current()->currency;

    // Activity timeline: merge subscription and payment events by date —
    // these fully describe a member's history (§19.3), no need to touch
    // audit_logs, which tracks staff actions, not member-centric events.
    $timeline = collect();
    foreach ($member->subscriptions as $subscription) {
        $timeline->push(['date' => $subscription->created_at, 'text' => __('Subscribed to :plan', ['plan' => $subscription->plan->plan_name])]);
        if ($subscription->status === \App\Enums\MembershipStatus::Expired && $subscription->expiry_date->isPast()) {
            $timeline->push(['date' => $subscription->expiry_date, 'text' => __('Membership expired')]);
        }
    }
    foreach ($member->payments as $payment) {
        $timeline->push(['date' => $payment->created_at, 'text' => __('Paid :currency :amount', ['currency' => $currency, 'amount' => number_format($payment->amount, 2)])]);
    }
    $timeline = $timeline->sortByDesc('date');
@endphp

<x-app-layout>
    <div class="space-y-6">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <div class="flex items-center gap-3">
                    <h1 class="text-xl font-semibold text-gray-900 dark:text-gray-100">{{ $member->full_name }}</h1>
                    @if ($member->trashed())
                        <span class="inline-flex px-2 py-0.5 rounded-full text-xs font-medium bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-300">{{ __('Archived') }}</span>
                    @elseif ($member->status === \App\Enums\MembershipStatus::Active)
                        <span class="inline-flex px-2 py-0.5 rounded-full text-xs font-medium bg-green-100 dark:bg-green-500/10 text-green-700 dark:text-green-400">{{ __('Active') }}</span>
                    @else
                        <span class="inline-flex px-2 py-0.5 rounded-full text-xs font-medium bg-red-100 dark:bg-red-500/10 text-red-700 dark:text-red-400">{{ __('Expired') }}</span>
                    @endif
                </div>
                <p class="text-sm text-gray-500 dark:text-gray-400">{{ $member->phone_number }} &middot; {{ __('created by') }} {{ $member->createdBy->name }}</p>
            </div>

            @unless ($member->trashed())
                <div class="flex gap-2">
                    <button onclick="Livewire.dispatch('edit-member', { memberId: {{ $member->id }} })" class="px-3 py-2 rounded-md border border-gray-300 dark:border-gray-600 text-sm font-medium text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-700">
                        {{ __('Edit') }}
                    </button>
                    <button onclick="Livewire.dispatch('renew-member', { memberId: {{ $member->id }} })" class="px-3 py-2 rounded-md bg-indigo-600 text-white text-sm font-medium hover:bg-indigo-500">
                        {{ __('Renew') }}
                    </button>
                </div>
            @endunless
        </div>

        <div class="grid lg:grid-cols-3 gap-6">
            <div class="lg:col-span-2 space-y-6">
                <div class="rounded-lg border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 overflow-hidden">
                    <h2 class="px-5 py-3 text-sm font-medium text-gray-700 dark:text-gray-200 border-b border-gray-100 dark:border-gray-700">{{ __('Subscription History') }}</h2>
                    <table class="min-w-full divide-y divide-gray-100 dark:divide-gray-700 text-sm">
                        <thead class="bg-gray-50 dark:bg-gray-700/50 text-xs uppercase text-gray-500 dark:text-gray-400">
                            <tr>
                                <th class="px-4 py-2 text-left">{{ __('Plan') }}</th>
                                <th class="px-4 py-2 text-left">{{ __('Period') }}</th>
                                <th class="px-4 py-2 text-left">{{ __('Paid / Balance') }}</th>
                                <th class="px-4 py-2 text-left">{{ __('Status') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                            @forelse ($member->subscriptions->sortByDesc('start_date') as $subscription)
                                <tr>
                                    <td class="px-4 py-3 text-gray-800 dark:text-gray-100">{{ $subscription->plan->plan_name }}</td>
                                    <td class="px-4 py-3 text-gray-500 dark:text-gray-400">{{ $subscription->start_date->format('M j, Y') }} – {{ $subscription->expiry_date->format('M j, Y') }}</td>
                                    <td class="px-4 py-3 text-gray-500 dark:text-gray-400 tabular-nums">{{ $currency }} {{ number_format($subscription->amount_paid, 2) }} / {{ number_format($subscription->balance, 2) }}</td>
                                    <td class="px-4 py-3">
                                        <span class="{{ $subscription->status === \App\Enums\MembershipStatus::Active ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400' }}">
                                            {{ ucfirst($subscription->status->value) }}
                                        </span>
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="4" class="px-4 py-6 text-center text-gray-400 dark:text-gray-500">{{ __('No subscriptions yet.') }}</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <div class="rounded-lg border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 overflow-hidden">
                    <h2 class="px-5 py-3 text-sm font-medium text-gray-700 dark:text-gray-200 border-b border-gray-100 dark:border-gray-700">{{ __('Payment History') }}</h2>
                    <table class="min-w-full divide-y divide-gray-100 dark:divide-gray-700 text-sm">
                        <thead class="bg-gray-50 dark:bg-gray-700/50 text-xs uppercase text-gray-500 dark:text-gray-400">
                            <tr>
                                <th class="px-4 py-2 text-left">{{ __('Date') }}</th>
                                <th class="px-4 py-2 text-left">{{ __('Amount') }}</th>
                                <th class="px-4 py-2 text-left">{{ __('Received By') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                            @forelse ($member->payments->sortByDesc('payment_date') as $payment)
                                <tr>
                                    <td class="px-4 py-3 text-gray-500 dark:text-gray-400">{{ $payment->payment_date->format('M j, Y') }}</td>
                                    <td class="px-4 py-3 text-gray-800 dark:text-gray-100 tabular-nums">{{ $currency }} {{ number_format($payment->amount, 2) }}</td>
                                    <td class="px-4 py-3 text-gray-500 dark:text-gray-400">{{ $payment->receivedBy->name }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="3" class="px-4 py-6 text-center text-gray-400 dark:text-gray-500">{{ __('No payments yet.') }}</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="space-y-6">
                @unless ($member->trashed())
                    <livewire:payment-form :member="$member" />
                @endunless

                <div class="rounded-lg border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 p-5">
                    <h2 class="text-sm font-medium text-gray-700 dark:text-gray-200 mb-4">{{ __('Activity Timeline') }}</h2>
                    <ul class="space-y-3 text-sm">
                        @forelse ($timeline as $event)
                            <li class="flex gap-2">
                                <span class="text-green-500">✓</span>
                                <div>
                                    <p class="text-gray-700 dark:text-gray-200">{{ $event['text'] }}</p>
                                    <p class="text-xs text-gray-400 dark:text-gray-500">{{ $event['date']->format('M j, Y') }}</p>
                                </div>
                            </li>
                        @empty
                            <li class="text-gray-400 dark:text-gray-500">{{ __('No activity yet.') }}</li>
                        @endforelse
                    </ul>
                </div>
            </div>
        </div>
    </div>

    <livewire:member-form />
    <livewire:renewal-modal />

    {{-- This page is a plain Blade view around several Livewire islands
         (PaymentForm/MemberForm/RenewalModal) — only they re-render on their
         own actions, so the surrounding static tables/timeline need a reload
         to reflect a change made through one of them. --}}
    <script>
        window.addEventListener('payment-recorded', () => window.location.reload());
        window.addEventListener('member-saved', () => window.location.reload());
        window.addEventListener('member-renewed', () => window.location.reload());
    </script>
</x-app-layout>
