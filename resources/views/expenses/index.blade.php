@php $currency = \App\Models\ApplicationSetting::current()->currency; @endphp

<x-app-layout>
    <div class="space-y-4">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <h1 class="text-xl font-semibold text-gray-900 dark:text-gray-100">{{ __('Expenses') }}</h1>
        </div>

        <div class="grid lg:grid-cols-2 gap-6">
            {{-- Left half: Categories --}}
            <div class="space-y-4" x-data="{ showCreate: {{ $errors->has('name') ? 'true' : 'false' }}, editing: null }">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <h2 class="text-sm font-medium text-gray-700 dark:text-gray-200">{{ __('Categories') }}</h2>
                    <button @click="showCreate = ! showCreate" class="inline-flex items-center px-3 py-2 rounded-md bg-indigo-600 text-white text-sm font-medium hover:bg-indigo-500">
                        + {{ __('Add Category') }}
                    </button>
                </div>

                <x-slide-over title="{{ __('New Category') }}">
                    <form method="POST" action="{{ route('expense-categories.store') }}" class="space-y-4">
                        @csrf
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-200">{{ __('Name') }}</label>
                            <input type="text" name="name" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                            @error('name') <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
                        </div>
                        <button type="submit" class="inline-flex items-center px-4 py-2 rounded-md bg-indigo-600 text-white text-sm font-medium hover:bg-indigo-500">{{ __('Create') }}</button>
                    </form>
                </x-slide-over>

                <div class="overflow-x-auto rounded-lg border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800">
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700 text-sm">
                        <thead class="bg-gray-50 dark:bg-gray-700/50">
                            <tr class="text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                <th class="px-4 py-3">{{ __('Name') }}</th>
                                <th class="px-4 py-3 text-right">{{ __('Actions') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                            @forelse ($categories as $category)
                                <tr>
                                    <td class="px-4 py-3 font-medium text-gray-900 dark:text-gray-100">{{ $category->name }}</td>
                                    <td class="px-4 py-3">
                                        <div class="flex justify-end gap-2 text-xs font-medium">
                                            <button @click="editing = editing === {{ $category->id }} ? null : {{ $category->id }}" class="text-indigo-600 dark:text-indigo-400 hover:underline">{{ __('Edit') }}</button>
                                            <form method="POST" action="{{ route('expense-categories.destroy', $category) }}" onsubmit="return confirm('{{ __('Delete :name?', ['name' => $category->name]) }}')">
                                                @csrf @method('DELETE')
                                                <button type="submit" class="text-red-600 dark:text-red-400 hover:underline">{{ __('Delete') }}</button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                                <tr x-show="editing === {{ $category->id }}" x-cloak>
                                    <td colspan="2" class="px-4 py-4 bg-gray-50 dark:bg-gray-700/30">
                                        <form method="POST" action="{{ route('expense-categories.update', $category) }}" class="flex gap-3 items-end">
                                            @csrf @method('PUT')
                                            <input type="text" name="name" value="{{ $category->name }}" class="flex-1 rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 text-sm">
                                            <button type="submit" class="inline-flex items-center px-3 py-2 rounded-md bg-indigo-600 text-white text-sm font-medium hover:bg-indigo-500">{{ __('Save') }}</button>
                                        </form>
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="2" class="px-4 py-10 text-center text-gray-400 dark:text-gray-500">{{ __('No categories found.') }}</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            {{-- Right half: Expenses --}}
            <div class="space-y-4" x-data="{ showCreate: {{ $errors->hasAny(['expense_category_id', 'amount', 'expense_date']) ? 'true' : 'false' }}, editing: null }">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <h2 class="text-sm font-medium text-gray-700 dark:text-gray-200">{{ __('Expense List') }}</h2>
                    <button @click="showCreate = ! showCreate" class="inline-flex items-center px-3 py-2 rounded-md bg-indigo-600 text-white text-sm font-medium hover:bg-indigo-500">
                        + {{ __('Add Expense') }}
                    </button>
                </div>

                <form method="GET" action="{{ route('expenses.index') }}">
                    <input type="text" name="search" value="{{ request('search') }}" placeholder="{{ __('Search by category…') }}" onchange="this.form.submit()" class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-100 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                </form>

                <x-slide-over title="{{ __('New Expense') }}">
                    <form method="POST" action="{{ route('expenses.store') }}" class="space-y-4">
                        @csrf
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-200">{{ __('Category') }}</label>
                            <select name="expense_category_id" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                <option value="">{{ __('Select a category…') }}</option>
                                @foreach ($categories as $category)
                                    <option value="{{ $category->id }}">{{ $category->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-200">{{ __('Amount') }}</label>
                            <input type="number" step="0.01" name="amount" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-200">{{ __('Date') }}</label>
                            <input type="date" name="expense_date" value="{{ now()->toDateString() }}" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                        </div>
                        <div>
                            @foreach (['expense_category_id', 'amount', 'expense_date'] as $field)
                                @error($field) <p class="text-sm text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
                            @endforeach
                        </div>
                        <div>
                            <button type="submit" class="inline-flex items-center px-4 py-2 rounded-md bg-indigo-600 text-white text-sm font-medium hover:bg-indigo-500">{{ __('Log Expense') }}</button>
                        </div>
                    </form>
                </x-slide-over>

                <div class="overflow-x-auto rounded-lg border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800">
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700 text-sm">
                        <thead class="bg-gray-50 dark:bg-gray-700/50">
                            <tr class="text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                <th class="px-4 py-3">{{ __('Date') }}</th>
                                <th class="px-4 py-3">{{ __('Category') }}</th>
                                <th class="px-4 py-3">{{ __('Amount') }}</th>
                                <th class="px-4 py-3 text-right">{{ __('Actions') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                            @forelse ($expenses as $expense)
                                <tr>
                                    <td class="px-4 py-3 text-gray-500 dark:text-gray-400 whitespace-nowrap">{{ $expense->expense_date->format('M j, Y') }}</td>
                                    <td class="px-4 py-3 font-medium text-gray-900 dark:text-gray-100">{{ $expense->category->name }}</td>
                                    <td class="px-4 py-3 text-gray-800 dark:text-gray-100 tabular-nums">{{ $currency }} {{ number_format($expense->amount, 2) }}</td>
                                    <td class="px-4 py-3">
                                        <div class="flex justify-end gap-2 text-xs font-medium">
                                            <button @click="editing = editing === {{ $expense->id }} ? null : {{ $expense->id }}" class="text-indigo-600 dark:text-indigo-400 hover:underline">{{ __('Edit') }}</button>
                                            <form method="POST" action="{{ route('expenses.destroy', $expense) }}" onsubmit="return confirm('{{ __('Delete this expense?') }}')">
                                                @csrf @method('DELETE')
                                                <button type="submit" class="text-red-600 dark:text-red-400 hover:underline">{{ __('Delete') }}</button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                                <tr x-show="editing === {{ $expense->id }}" x-cloak>
                                    <td colspan="4" class="px-4 py-4 bg-gray-50 dark:bg-gray-700/30">
                                        <form method="POST" action="{{ route('expenses.update', $expense) }}" class="grid sm:grid-cols-4 gap-3 items-end">
                                            @csrf @method('PUT')
                                            <input type="date" name="expense_date" value="{{ $expense->expense_date->toDateString() }}" class="rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 text-sm">
                                            <select name="expense_category_id" class="rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 text-sm">
                                                @foreach ($categories as $category)
                                                    <option value="{{ $category->id }}" @selected($category->id === $expense->expense_category_id)>{{ $category->name }}</option>
                                                @endforeach
                                            </select>
                                            <input type="number" step="0.01" name="amount" value="{{ $expense->amount }}" class="rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 text-sm">
                                            <button type="submit" class="inline-flex items-center px-3 py-2 rounded-md bg-indigo-600 text-white text-sm font-medium hover:bg-indigo-500">{{ __('Save') }}</button>
                                        </form>
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="4" class="px-4 py-10 text-center text-gray-400 dark:text-gray-500">{{ __('No expenses found.') }}</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                {{ $expenses->links() }}
            </div>
        </div>
    </div>
</x-app-layout>
