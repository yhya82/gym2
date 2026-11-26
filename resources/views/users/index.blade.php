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
                <p class="text-xs text-gray-500 dark:text-gray-400"><span class="text-red-600 dark:text-red-400" aria-hidden="true">*</span> {{ __('Required') }}</p>
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-200">{{ __('Name') }} <span class="text-red-600 dark:text-red-400" aria-hidden="true">*</span></label>
                    <input type="text" name="name" required aria-required="true" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    @error('name') <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-200">{{ __('Email') }} <span class="text-red-600 dark:text-red-400" aria-hidden="true">*</span></label>
                    <input type="email" name="email" required aria-required="true" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    @error('email') <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-200">{{ __('Phone Number') }} <span class="text-red-600 dark:text-red-400" aria-hidden="true">*</span></label>
                    <input type="text" name="phone_number" required aria-required="true" placeholder="835190199" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    @error('phone_number') <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
                </div>
                {{-- Local Alpine state only drives the live checklist and the
                     "don't match" hint; the server validates on submit. --}}
                <div x-data="{ password: '', confirmation: '' }" class="space-y-4">
                    <div>
                        <label for="new-user-password" class="block text-sm font-medium text-gray-700 dark:text-gray-200">{{ __('Password') }} <span class="text-red-600 dark:text-red-400" aria-hidden="true">*</span></label>
                        <input id="new-user-password" required aria-required="true" type="password" name="password" x-model="password" autocomplete="new-password" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                        @error('password') <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
                        <x-password-requirements value="password" class="mt-2" />
                    </div>
                    <div>
                        <label for="new-user-password-confirmation" class="block text-sm font-medium text-gray-700 dark:text-gray-200">{{ __('Confirm password') }} <span class="text-red-600 dark:text-red-400" aria-hidden="true">*</span></label>
                        <input id="new-user-password-confirmation" required aria-required="true" type="password" name="password_confirmation" x-model="confirmation" autocomplete="new-password" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                        <p x-show="confirmation && password !== confirmation" x-cloak class="mt-1 text-sm text-red-600 dark:text-red-400">{{ __("The passwords don't match.") }}</p>
                    </div>
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
                            <td class="px-4 py-3 font-medium">
                                {{-- The name is the way into a user's profile (users.show). --}}
                                <a href="{{ route('users.show', $user) }}" wire:navigate title="{{ __('View :name\'s profile', ['name' => $user->name]) }}" class="text-indigo-700 dark:text-indigo-300 hover:text-indigo-900 dark:hover:text-indigo-200 hover:underline">{{ $user->name }}</a>
                                @if ($user->isLocked())
                                    <span class="ml-1.5 rounded-full bg-amber-100 dark:bg-amber-500/10 px-2 py-0.5 text-xs font-semibold text-amber-800 dark:text-amber-400">{{ __('Locked') }}</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-gray-500 dark:text-gray-400">{{ $user->email }}</td>
                            <td class="px-4 py-3 text-gray-500 dark:text-gray-400 whitespace-nowrap">{{ $user->phone_number_formatted ?? '—' }}</td>
                            <td class="px-4 py-3 text-gray-500 dark:text-gray-400">{{ $user->role->value === 'receptionist' ? __('Manager') : __('Admin') }}</td>
                            <td class="px-4 py-3">
                                <div class="flex flex-wrap items-center justify-end gap-x-3 gap-y-2 text-xs font-medium">
                                    {{-- Same size/shape as <x-change-password-button size="sm">:
                                         indigo = edit, amber = password, red = deactivate. --}}
                                    <button type="button" @click="editing = editing === {{ $user->id }} ? null : {{ $user->id }}"
                                            class="inline-flex items-center gap-1.5 rounded-md border border-indigo-300 bg-indigo-50 px-2.5 py-1 text-xs font-semibold text-indigo-700 transition-colors hover:border-indigo-400 hover:bg-indigo-100 focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500 focus-visible:ring-offset-1 dark:border-indigo-500/40 dark:bg-indigo-500/10 dark:text-indigo-300 dark:hover:bg-indigo-500/20">
                                        <x-icon name="pencil" class="h-3.5 w-3.5 shrink-0" aria-hidden="true" />{{ __('Edit') }}
                                    </button>
                                    @can('changePassword', $user)
                                        <x-change-password-button :user-id="$user->id === auth()->id() ? null : $user->id" size="sm" />
                                    @endcan
                                    @unless ($user->id === auth()->id())
                                        <form method="POST" action="{{ route('users.destroy', $user) }}" onsubmit="return confirm('{{ __('Deactivate :name?', ['name' => $user->name]) }}')">
                                            @csrf @method('DELETE')
                                            <button type="submit"
                                                    class="inline-flex items-center gap-1.5 rounded-md border border-red-300 bg-red-50 px-2.5 py-1 text-xs font-semibold text-red-700 transition-colors hover:border-red-400 hover:bg-red-100 focus:outline-none focus-visible:ring-2 focus-visible:ring-red-500 focus-visible:ring-offset-1 dark:border-red-500/40 dark:bg-red-500/10 dark:text-red-300 dark:hover:bg-red-500/20">
                                                <x-icon name="ban" class="h-3.5 w-3.5 shrink-0" aria-hidden="true" />{{ __('Deactivate') }}
                                            </button>
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
                    <p class="text-xs text-gray-500 dark:text-gray-400"><span class="text-red-600 dark:text-red-400" aria-hidden="true">*</span> {{ __('Required') }}</p>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-200">{{ __('Name') }} <span class="text-red-600 dark:text-red-400" aria-hidden="true">*</span></label>
                        <input type="text" name="name" required aria-required="true" value="{{ $user->name }}" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                        @error('name') <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-200">{{ __('Email') }} <span class="text-red-600 dark:text-red-400" aria-hidden="true">*</span></label>
                        <input type="email" name="email" required aria-required="true" value="{{ $user->email }}" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                        @error('email') <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-200">{{ __('Phone Number') }} <span class="text-red-600 dark:text-red-400" aria-hidden="true">*</span></label>
                        <input type="text" name="phone_number" required aria-required="true" value="{{ $user->phone_number }}" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                        @error('phone_number') <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
                    </div>
                    @php $roleDecision = Gate::inspect('changeRole', $user); @endphp
                    <div>
                        <label for="role-{{ $user->id }}" class="block text-sm font-medium text-gray-700 dark:text-gray-200">{{ __('Role') }}</label>
                        {{-- Disabled (and so not submitted) when UserPolicy::changeRole
                             says no; UserController::update re-checks regardless. --}}
                        <select id="role-{{ $user->id }}" name="role" @disabled($roleDecision->denied())
                                @if ($roleDecision->denied()) aria-describedby="role-note-{{ $user->id }}" @endif
                                class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 disabled:bg-gray-100 disabled:text-gray-500 dark:disabled:bg-gray-800">
                            <option value="receptionist" @selected($user->role->value === 'receptionist')>{{ __('Manager') }}</option>
                            <option value="admin" @selected($user->role->value === 'admin')>{{ __('Admin') }}</option>
                        </select>
                        @if ($roleDecision->denied())
                            <p id="role-note-{{ $user->id }}" class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ $roleDecision->message() }}</p>
                        @endif
                        @error('role') <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
                    </div>
                    <p class="text-xs text-gray-500 dark:text-gray-400">{{ __('To change this user\'s password, use "Change password" in the list.') }}</p>
                    <div>
                        <button type="submit" class="inline-flex items-center px-4 py-2 rounded-md bg-indigo-600 text-white text-sm font-medium hover:bg-indigo-500">{{ __('Save Changes') }}</button>
                    </div>
                </form>
            </x-slide-over>
        @endforeach
    </div>

    <livewire:change-password-modal />

    <div x-data="{ message: '' }" x-on:password-updated.window="message = $event.detail.message; setTimeout(() => message = '', 5000)"
         class="fixed bottom-4 right-4 z-50 max-w-sm">
        <div x-show="message" x-cloak x-transition role="status" x-text="message"
             class="rounded-md bg-green-50 dark:bg-green-900 border border-green-200 dark:border-green-700 px-4 py-3 text-sm text-green-800 dark:text-green-200 shadow-lg"></div>
    </div>
</x-app-layout>
