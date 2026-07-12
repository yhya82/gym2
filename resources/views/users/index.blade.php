<x-app-layout>
    <div class="space-y-4" x-data="{ showCreate: false, editing: null }">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <h1 class="text-xl font-semibold text-gray-900 dark:text-gray-100">{{ __('Users') }}</h1>
            <button @click="showCreate = ! showCreate" class="inline-flex items-center px-4 py-2 rounded-md bg-indigo-600 text-white text-sm font-medium hover:bg-indigo-500">
                + {{ __('Add User') }}
            </button>
        </div>

        <form method="GET" action="{{ route('users.index') }}">
            <input type="text" name="search" value="{{ request('search') }}" placeholder="{{ __('Search by name or email…') }}" onchange="this.form.submit()" class="w-full max-w-sm rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-100 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
        </form>

        <div x-show="showCreate" x-cloak class="rounded-lg border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 p-5">
            <h2 class="text-sm font-medium text-gray-700 dark:text-gray-200 mb-4">{{ __('New User') }}</h2>
            <form method="POST" action="{{ route('users.store') }}" class="grid sm:grid-cols-4 gap-4">
                @csrf
                <input type="text" name="name" placeholder="{{ __('Name') }}" class="rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 text-sm">
                <input type="email" name="email" placeholder="{{ __('Email') }}" class="rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 text-sm">
                <input type="password" name="password" placeholder="{{ __('Password') }}" class="rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 text-sm">
                <select name="role" class="rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 text-sm">
                    <option value="receptionist">{{ __('Receptionist') }}</option>
                    <option value="admin">{{ __('Admin') }}</option>
                </select>
                <div class="sm:col-span-4">
                    @foreach (['name', 'email', 'password', 'role'] as $field)
                        @error($field) <p class="text-sm text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
                    @endforeach
                </div>
                <div class="sm:col-span-4">
                    <button type="submit" class="inline-flex items-center px-4 py-2 rounded-md bg-indigo-600 text-white text-sm font-medium hover:bg-indigo-500">{{ __('Create User') }}</button>
                </div>
            </form>
        </div>

        <div class="overflow-x-auto rounded-lg border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800">
            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700 text-sm">
                <thead class="bg-gray-50 dark:bg-gray-700/50">
                    <tr class="text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                        <th class="px-4 py-3">{{ __('Name') }}</th>
                        <th class="px-4 py-3">{{ __('Email') }}</th>
                        <th class="px-4 py-3">{{ __('Role') }}</th>
                        <th class="px-4 py-3 text-right">{{ __('Actions') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                    @forelse ($users as $user)
                        <tr>
                            <td class="px-4 py-3 font-medium text-gray-900 dark:text-gray-100">{{ $user->name }}</td>
                            <td class="px-4 py-3 text-gray-500 dark:text-gray-400">{{ $user->email }}</td>
                            <td class="px-4 py-3 text-gray-500 dark:text-gray-400 capitalize">{{ $user->role->value }}</td>
                            <td class="px-4 py-3">
                                <div class="flex justify-end gap-2 text-xs font-medium">
                                    <button @click="editing = editing === {{ $user->id }} ? null : {{ $user->id }}" class="text-indigo-600 dark:text-indigo-400 hover:underline">{{ __('Edit') }}</button>
                                    @unless ($user->id === auth()->id())
                                        <form method="POST" action="{{ route('users.destroy', $user) }}" onsubmit="return confirm('{{ __('Deactivate :name?', ['name' => $user->name]) }}')">
                                            @csrf @method('DELETE')
                                            <button type="submit" class="text-red-600 dark:text-red-400 hover:underline">{{ __('Deactivate') }}</button>
                                        </form>
                                    @endunless
                                </div>
                            </td>
                        </tr>
                        <tr x-show="editing === {{ $user->id }}" x-cloak>
                            <td colspan="4" class="px-4 py-4 bg-gray-50 dark:bg-gray-700/30">
                                <form method="POST" action="{{ route('users.update', $user) }}" class="grid sm:grid-cols-5 gap-3 items-end">
                                    @csrf @method('PUT')
                                    <input type="text" name="name" value="{{ $user->name }}" class="rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 text-sm">
                                    <input type="email" name="email" value="{{ $user->email }}" class="rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 text-sm">
                                    <input type="password" name="password" placeholder="{{ __('New password (optional)') }}" class="rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 text-sm">
                                    <select name="role" class="rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 text-sm">
                                        <option value="receptionist" @selected($user->role->value === 'receptionist')>{{ __('Receptionist') }}</option>
                                        <option value="admin" @selected($user->role->value === 'admin')>{{ __('Admin') }}</option>
                                    </select>
                                    <button type="submit" class="inline-flex items-center px-3 py-2 rounded-md bg-indigo-600 text-white text-sm font-medium hover:bg-indigo-500">{{ __('Save') }}</button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="px-4 py-10 text-center text-gray-400 dark:text-gray-500">{{ __('No users found.') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{ $users->links() }}
    </div>
</x-app-layout>
