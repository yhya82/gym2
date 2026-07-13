@php
    $isAdmin = auth()->user()->role === \App\Enums\UserRole::Admin;
    $currency = \App\Models\ApplicationSetting::current()->currency;
@endphp

<div class="space-y-4">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <h1 class="text-xl font-semibold text-gray-900 dark:text-gray-100">{{ __('Members') }}</h1>
        <a href="{{ route('members.create') }}" wire:navigate class="inline-flex items-center px-4 py-2 rounded-md bg-indigo-600 text-white text-sm font-medium hover:bg-indigo-500">
            + {{ __('Create Member') }}
        </a>
    </div>

    <div class="flex flex-wrap gap-3">
        <input
            type="text"
            wire:model.live.debounce.400ms="search"
            placeholder="{{ __('Search by name or phone…') }}"
            class="flex-1 min-w-[200px] rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-100 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
        >

        <select wire:model.live="status" class="rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-100 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
            <option value="all">{{ __('All Members') }}</option>
            <option value="active">{{ __('Active Members') }}</option>
            <option value="expired">{{ __('Expired Members') }}</option>
            @if ($isAdmin)
                <option value="archived">{{ __('Archived Members') }}</option>
            @endif
        </select>
    </div>

    <div class="overflow-x-auto rounded-lg border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800">
        <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700 text-sm">
            <thead class="bg-gray-50 dark:bg-gray-700/50">
                <tr class="text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                    <th class="px-4 py-3">{{ __('Name') }}</th>
                    <th class="px-4 py-3">{{ __('Phone') }}</th>
                    <th class="px-4 py-3">{{ __('Plan') }}</th>
                    <th class="px-4 py-3">{{ __('Plan Price') }}</th>
                    <th class="px-4 py-3">{{ __('Amount Paid') }}</th>
                    <th class="px-4 py-3">{{ __('Status') }}</th>
                    <th class="px-4 py-3">{{ __('Expiry') }}</th>
                    <th class="px-4 py-3 text-right">{{ __('Actions') }}</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                @forelse ($this->members as $member)
                    <tr wire:key="member-{{ $member->id }}">
                        <td class="px-4 py-3 font-medium text-gray-900 dark:text-gray-100">
                            <a href="{{ route('members.show', $member) }}" wire:navigate class="hover:text-indigo-600 dark:hover:text-indigo-400">
                                {{ $member->full_name }}
                            </a>
                        </td>
                        <td class="px-4 py-3 text-gray-500 dark:text-gray-400">{{ $member->phone_number }}</td>
                        <td class="px-4 py-3 text-gray-500 dark:text-gray-400">{{ $member->currentSubscription?->plan?->plan_name ?? '—' }}</td>
                        <td class="px-4 py-3 text-gray-500 dark:text-gray-400 tabular-nums">
                            {{ $member->currentSubscription ? $currency.' '.number_format($member->currentSubscription->plan_price, 2) : '—' }}
                        </td>
                        <td class="px-4 py-3 text-gray-500 dark:text-gray-400 tabular-nums">
                            {{ $member->currentSubscription ? $currency.' '.number_format($member->currentSubscription->amount_paid, 2) : '—' }}
                        </td>
                        <td class="px-4 py-3">
                            @if ($member->trashed())
                                <span class="inline-flex px-2 py-0.5 rounded-full text-xs font-medium bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-300">{{ __('Archived') }}</span>
                            @elseif ($member->status === \App\Enums\MembershipStatus::Active)
                                <span class="inline-flex px-2 py-0.5 rounded-full text-xs font-medium bg-green-100 dark:bg-green-500/10 text-green-700 dark:text-green-400">{{ __('Active') }}</span>
                            @else
                                <span class="inline-flex px-2 py-0.5 rounded-full text-xs font-medium bg-red-100 dark:bg-red-500/10 text-red-700 dark:text-red-400">{{ __('Expired') }}</span>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-gray-500 dark:text-gray-400">{{ $member->currentSubscription?->expiry_date?->format('M j, Y') ?? '—' }}</td>
                        <td class="px-4 py-3">
                            <div class="flex justify-end gap-2 text-xs font-medium">
                                @if (! $member->trashed())
                                    <button wire:click="$dispatch('edit-member', { memberId: {{ $member->id }} })" class="text-indigo-600 dark:text-indigo-400 hover:underline">{{ __('Edit') }}</button>
                                    <button wire:click="$dispatch('renew-member', { memberId: {{ $member->id }} })" class="text-indigo-600 dark:text-indigo-400 hover:underline">{{ __('Renew') }}</button>
                                    @if ($isAdmin)
                                        <button
                                            wire:click="archive({{ $member->id }})"
                                            wire:confirm="{{ __('Archive :name? History will remain.', ['name' => $member->full_name]) }}"
                                            class="text-red-600 dark:text-red-400 hover:underline"
                                        >{{ __('Archive') }}</button>
                                    @endif
                                @elseif ($isAdmin)
                                    <button wire:click="restore({{ $member->id }})" class="text-indigo-600 dark:text-indigo-400 hover:underline">{{ __('Restore') }}</button>
                                @endif
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" class="px-4 py-10 text-center text-gray-400 dark:text-gray-500">
                            {{ __('No members found.') }}
                            @if (! $search && $status === 'all')
                                <br>{{ __('Create your first member to get started.') }}
                            @endif
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{ $this->members->links() }}

    <livewire:member-form />
    <livewire:renewal-modal />
</div>
