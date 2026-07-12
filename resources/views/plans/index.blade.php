<x-app-layout>
    <div class="space-y-4" x-data="{ showCreate: false, editing: null }">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <h1 class="text-xl font-semibold text-gray-900 dark:text-gray-100">{{ __('Plans') }}</h1>
            <button @click="showCreate = ! showCreate" class="inline-flex items-center px-4 py-2 rounded-md bg-indigo-600 text-white text-sm font-medium hover:bg-indigo-500">
                + {{ __('Add Plan') }}
            </button>
        </div>

        <form method="GET" action="{{ route('plans.index') }}">
            <input type="text" name="search" value="{{ request('search') }}" placeholder="{{ __('Search plans…') }}" onchange="this.form.submit()" class="w-full max-w-sm rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-100 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
        </form>

        <div x-show="showCreate" x-cloak class="rounded-lg border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 p-5">
            <h2 class="text-sm font-medium text-gray-700 dark:text-gray-200 mb-4">{{ __('New Plan') }}</h2>
            <form method="POST" action="{{ route('plans.store') }}" class="grid sm:grid-cols-3 gap-4">
                @csrf
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-200">{{ __('Plan Name') }}</label>
                    <input type="text" name="plan_name" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-200">{{ __('Duration (days)') }}</label>
                    <input type="number" name="duration_days" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-200">{{ __('Price') }}</label>
                    <input type="number" step="0.01" name="price" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                </div>
                <div class="sm:col-span-3">
                    @foreach (['plan_name', 'duration_days', 'price'] as $field)
                        @error($field) <p class="text-sm text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
                    @endforeach
                </div>
                <div class="sm:col-span-3">
                    <button type="submit" class="inline-flex items-center px-4 py-2 rounded-md bg-indigo-600 text-white text-sm font-medium hover:bg-indigo-500">{{ __('Create Plan') }}</button>
                </div>
            </form>
        </div>

        <div class="overflow-x-auto rounded-lg border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800">
            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700 text-sm">
                <thead class="bg-gray-50 dark:bg-gray-700/50">
                    <tr class="text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                        <th class="px-4 py-3">{{ __('Plan') }}</th>
                        <th class="px-4 py-3">{{ __('Duration') }}</th>
                        <th class="px-4 py-3">{{ __('Price') }}</th>
                        <th class="px-4 py-3 text-right">{{ __('Actions') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                    @forelse ($plans as $plan)
                        <tr>
                            <td class="px-4 py-3 font-medium text-gray-900 dark:text-gray-100">{{ $plan->plan_name }}</td>
                            <td class="px-4 py-3 text-gray-500 dark:text-gray-400">{{ $plan->duration_days }} {{ __('days') }}</td>
                            <td class="px-4 py-3 text-gray-500 dark:text-gray-400 tabular-nums">{{ number_format($plan->price, 2) }}</td>
                            <td class="px-4 py-3">
                                <div class="flex justify-end gap-2 text-xs font-medium">
                                    <button @click="editing = editing === {{ $plan->id }} ? null : {{ $plan->id }}" class="text-indigo-600 dark:text-indigo-400 hover:underline">{{ __('Edit') }}</button>
                                    <form method="POST" action="{{ route('plans.destroy', $plan) }}" onsubmit="return confirm('{{ __('Delete :name?', ['name' => $plan->plan_name]) }}')">
                                        @csrf @method('DELETE')
                                        <button type="submit" class="text-red-600 dark:text-red-400 hover:underline">{{ __('Delete') }}</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                        <tr x-show="editing === {{ $plan->id }}" x-cloak>
                            <td colspan="4" class="px-4 py-4 bg-gray-50 dark:bg-gray-700/30">
                                <form method="POST" action="{{ route('plans.update', $plan) }}" class="grid sm:grid-cols-4 gap-3 items-end">
                                    @csrf @method('PUT')
                                    <input type="text" name="plan_name" value="{{ $plan->plan_name }}" class="rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 text-sm">
                                    <input type="number" name="duration_days" value="{{ $plan->duration_days }}" class="rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 text-sm">
                                    <input type="number" step="0.01" name="price" value="{{ $plan->price }}" class="rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 text-sm">
                                    <button type="submit" class="inline-flex items-center px-3 py-2 rounded-md bg-indigo-600 text-white text-sm font-medium hover:bg-indigo-500">{{ __('Save') }}</button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="px-4 py-10 text-center text-gray-400 dark:text-gray-500">{{ __('No plans found.') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{ $plans->links() }}
    </div>
</x-app-layout>
