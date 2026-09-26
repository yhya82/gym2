<x-app-layout>
    <div class="space-y-4" x-data="{ showCreate: {{ $errors->hasAny(['name', 'email', 'phone_number', 'password', 'role']) ? 'true' : 'false' }}, editing: null }">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <h1 class="text-xl font-semibold text-gray-900 dark:text-gray-100">{{ __('Users') }}</h1>
            <button @click="showCreate = ! showCreate" class="inline-flex items-center px-4 py-2 rounded-md bg-indigo-600 text-white text-sm font-medium hover:bg-indigo-500">
                + {{ __('Add User') }}
            </button>
        </div>

        <form method="GET" action="{{ route('users.index') }}">
            <input type="text" name="search" value="{{ request('search') }}" placeholder="{{ __('Search by name or email…') }}" onchange="this.form.submit()" class="w-full max-w-sm rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-100 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
        </form>

        <x-slide-over title="{{ __('New User') }}">
            <form method="POST" action="{{ route('users.store') }}" class="space-y-4">
                @csrf
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-200">{{ __('Name') }}</label>
                    <input type="text" name="name" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    @error('name') <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-200">{{ __('Email') }}</label>
                    <input type="email" name="email" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    @error('email') <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-200">{{ __('Phone Number') }}</label>
                    <input type="text" name="phone_number" placeholder="835190199" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    @error('phone_number') <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-200">{{ __('Password') }}</label>
                    <input type="password" name="password" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    @error('password') <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-200">{{ __('Role') }}</label>
                    <select name="role" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                        <option value="receptionist">{{ __('Manager') }}</option>
                        <option value="admin">{{ __('Admin') }}</option>
                    </select>
                    @error('role') <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
                </div>
                <div>
                    <button type="submit" class="inline-flex items-center px-4 py-2 rounded-md bg-indigo-600 text-white text-sm font-medium hover:bg-indigo-500">{{ __('Create User') }}</button>
                </div>
            </form>
        </x-slide-over>

        <div class="overflow-x-auto rounded-lg border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800">
            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700 text-sm">
                <thead class="bg-gray-50 dark:bg-gray-700/50">
                    <tr class="text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                        <th class="px-4 py-3">{{ __('Name') }}</th>
                        <th class="px-4 py-3">{{ __('Email') }}</th>
                        <th class="px-4 py-3">{{ __('Phone') }}</th>
                        <th class="px-4 py-3">{{ __('Role') }}</th>
                        <th class="px-4 py-3 text-right">{{ __('Actions') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                    @forelse ($users as $user)
                        <tr>
                            <td class="px-4 py-3 font-medium text-gray-900 dark:text-gray-100">{{ $user->name }}</td>
                            <td class="px-4 py-3 text-gray-500 dark:text-gray-400">{{ $user->email }}</td>
                            <td class="px-4 py-3 text-gray-500 dark:text-gray-400 whitespace-nowrap">{{ $user->phone_number_formatted ?? '—' }}</td>
                            <td class="px-4 py-3 text-gray-500 dark:text-gray-400">{{ $user->role->value === 'receptionist' ? __('Manager') : __('Admin') }}</td>
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
                    @empty
                        <tr><td colspan="5" class="px-4 py-10 text-center text-gray-400 dark:text-gray-500">{{ __('No users found.') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{ $users->links() }}

        {{-- One slide-over per row, each gated on its own row id rather than
             the shared `showCreate` the New User panel uses — a fixed-position
             overlay works from anywhere in the DOM, so these sit after the
             table instead of nested inside it. --}}
        @foreach ($users as $user)
            <x-slide-over
                title="{{ __('Edit :name', ['name' => $user->name]) }}"
                show="editing === {{ $user->id }}"
                close="editing = null"
            >
                <form method="POST" action="{{ route('users.update', $user) }}" class="space-y-4">
                    @csrf @method('PUT')
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-200">{{ __('Name') }}</label>
                        <input type="text" name="name" value="{{ $user->name }}" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                        @error('name') <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-200">{{ __('Email') }}</label>
                        <input type="email" name="email" value="{{ $user->email }}" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                        @error('email') <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-200">{{ __('Phone Number') }}</label>
                        <input type="text" name="phone_number" value="{{ $user->phone_number }}" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                        @error('phone_number') <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-200">{{ __('New Password') }}</label>
                        <input type="password" name="password" placeholder="{{ __('Leave blank to keep unchanged') }}" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                        @error('password') <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-200">{{ __('Role') }}</label>
                        <select name="role" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                            <option value="receptionist" @selected($user->role->value === 'receptionist')>{{ __('Manager') }}</option>
                            <option value="admin" @selected($user->role->value === 'admin')>{{ __('Admin') }}</option>
                        </select>
                        @error('role') <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <button type="submit" class="inline-flex items-center px-4 py-2 rounded-md bg-indigo-600 text-white text-sm font-medium hover:bg-indigo-500">{{ __('Save Changes') }}</button>
                    </div>
                </form>
            </x-slide-over>
        @endforeach
    </div>
</x-app-layout>
