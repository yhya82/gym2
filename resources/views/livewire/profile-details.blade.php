@php
    $user = $this->user;
    $isSelf = $this->isSelf;
    $isAdminRole = $user->role === \App\Enums\UserRole::Admin;
    $roleLabel = $isAdminRole ? __('Admin') : __('Manager');
    $roleDecision = $this->roleDecision;
    $inputClass = 'mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500';
    $lastLogin = $this->lastLogin ? \Illuminate\Support\Carbon::parse($this->lastLogin) : null;
    $passwordChangedAt = $this->passwordChangedAt ? \Illuminate\Support\Carbon::parse($this->passwordChangedAt) : null;
    $initials = \Illuminate\Support\Str::of($user->name)->explode(' ')->filter()->take(2)->map(fn ($p) => mb_strtoupper(mb_substr($p, 0, 1)))->implode('');
@endphp

<div class="space-y-4">
    <div x-data="{ message: '' }"
         x-on:password-updated.window="message = $event.detail.message; setTimeout(() => message = '', 5000)"
         x-on:profile-updated.window="message = @js($isSelf ? __('Your details were saved.') : __('Details saved.')); setTimeout(() => message = '', 4000)">
        <div x-show="message" x-cloak role="status" x-text="message"
             class="rounded-md bg-green-50 dark:bg-green-500/10 border border-green-200 dark:border-green-500/20 px-4 py-3 text-sm text-green-800 dark:text-green-300"></div>
    </div>

    <section aria-labelledby="details-heading" class="overflow-hidden rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 shadow-sm">
        {{-- Neutral header: plain white/grey, no colour band. The edit icon sits
             in the card's top-right corner. Status badges (locked, must change
             password) keep their amber because they flag something to act on. --}}
        <div class="flex items-center justify-between gap-4 px-6 py-5 border-b border-gray-200 dark:border-gray-700">
            <div class="flex items-center gap-4 min-w-0">
                <div aria-hidden="true" class="flex h-14 w-14 shrink-0 items-center justify-center rounded-full bg-gray-100 dark:bg-gray-700 text-lg font-semibold text-gray-700 dark:text-gray-200">
                    {{ $initials }}
                </div>
                <div class="min-w-0">
                    <div class="flex flex-wrap items-center gap-2">
                        <h2 id="details-heading" class="truncate text-lg font-semibold text-gray-900 dark:text-gray-100">{{ $user->name }}</h2>
                        <span class="inline-flex items-center gap-1 rounded-full bg-gray-100 dark:bg-gray-700 px-2.5 py-0.5 text-xs font-medium text-gray-700 dark:text-gray-300"><x-icon name="shield" class="h-3 w-3" aria-hidden="true" />{{ $roleLabel }}</span>
                        @if ($user->isLocked())
                            <span class="rounded-full bg-amber-100 dark:bg-amber-500/10 px-2 py-0.5 text-xs font-semibold text-amber-800 dark:text-amber-400">{{ __('Locked') }}</span>
                        @endif
                        @if ($user->must_change_password)
                            <span class="rounded-full bg-amber-100 dark:bg-amber-500/10 px-2 py-0.5 text-xs font-semibold text-amber-800 dark:text-amber-400">{{ __('Must change password') }}</span>
                        @endif
                        @if ($editing)
                            <span class="rounded-full bg-gray-100 dark:bg-gray-700 px-2 py-0.5 text-xs font-semibold text-gray-700 dark:text-gray-200">{{ __('Editing') }}</span>
                        @endif
                    </div>
                    <p class="text-sm text-gray-500 dark:text-gray-400">{{ $isSelf ? __('My details') : __('User details') }}</p>
                </div>
            </div>

            @if ($this->canEdit)
                <button
                    type="button"
                    wire:click="{{ $editing ? 'cancelEditing' : 'startEditing' }}"
                    aria-label="{{ $editing ? __('Stop editing details') : __('Edit details') }}"
                    title="{{ $editing ? __('Stop editing') : __('Edit details') }}"
                    aria-pressed="{{ $editing ? 'true' : 'false' }}"
                    @class([
                        'flex h-11 w-11 shrink-0 items-center justify-center rounded-lg border transition-colors',
                        'border-gray-900 bg-gray-900 text-white dark:border-gray-100 dark:bg-gray-100 dark:text-gray-900' => $editing,
                        'border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-700' => ! $editing,
                    ])
                >
                    <x-icon name="pencil" class="h-[18px] w-[18px]" stroke-width="2" aria-hidden="true" />
                </button>
            @endif
        </div>

        @if ($editing)
            <form wire:submit="save">
                <p class="px-6 pt-4 text-xs text-gray-500 dark:text-gray-400"><span class="text-red-600 dark:text-red-400" aria-hidden="true">*</span> {{ __('Required') }}</p>
                <div class="grid gap-4 px-6 pt-3 pb-5 sm:grid-cols-2">
                    <div class="sm:col-span-2">
                        <label for="pd-name" class="block text-sm font-medium text-gray-700 dark:text-gray-200">{{ __('Name') }} <span class="text-red-600 dark:text-red-400" aria-hidden="true">*</span></label>
                        <input id="pd-name" required aria-required="true" type="text" wire:model="name" autocomplete="name" class="{{ $inputClass }}">
                        @error('name') <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label for="pd-email" class="block text-sm font-medium text-gray-700 dark:text-gray-200">{{ __('Email') }} <span class="text-red-600 dark:text-red-400" aria-hidden="true">*</span></label>
                        <input id="pd-email" required aria-required="true" type="email" wire:model="email" autocomplete="email" class="{{ $inputClass }}">
                        @error('email') <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label for="pd-phone" class="block text-sm font-medium text-gray-700 dark:text-gray-200">{{ __('Phone number') }} <span class="text-red-600 dark:text-red-400" aria-hidden="true">*</span></label>
                        <input id="pd-phone" required aria-required="true" type="text" wire:model="phone_number" placeholder="835190199" autocomplete="tel" class="{{ $inputClass }}">
                        @error('phone_number') <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
                    </div>
                    <div class="sm:col-span-2">
                        @if ($roleDecision->allowed())
                            <label for="pd-role" class="block text-sm font-medium text-gray-700 dark:text-gray-200">{{ __('Role') }}</label>
                            <select id="pd-role" wire:model="role" class="{{ $inputClass }} sm:max-w-xs">
                                <option value="receptionist">{{ __('Manager') }}</option>
                                <option value="admin">{{ __('Admin') }}</option>
                            </select>
                        @else
                            <span class="block text-sm font-medium text-gray-700 dark:text-gray-200">{{ __('Role') }}</span>
                            <p class="mt-1 text-sm text-gray-900 dark:text-gray-100">{{ $roleLabel }} <span class="text-gray-500 dark:text-gray-400">· {{ $roleDecision->message() }}</span></p>
                        @endif
                        @error('role') <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
                    </div>
                </div>
                <div class="flex justify-end gap-3 px-6 py-4 border-t border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-700/40 rounded-b-lg">
                    <button type="button" wire:click="cancelEditing" class="min-h-[40px] px-4 rounded-md border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-sm font-medium text-gray-700 dark:text-gray-200 hover:bg-gray-50">{{ __('Cancel') }}</button>
                    <button type="submit" wire:loading.attr="disabled" class="min-h-[40px] px-4 rounded-md bg-indigo-600 text-sm font-semibold text-white hover:bg-indigo-500">{{ __('Save changes') }}</button>
                </div>
            </form>
        @else
            @php
                // [label, value, icon, tile colours] — one hue per kind of
                // detail so the grid scans quickly; values stay in plain
                // high-contrast text.
                $details = [
                    [__('Name'), $user->name, 'user', 'bg-indigo-50 text-indigo-600 dark:bg-indigo-500/10 dark:text-indigo-300'],
                    [__('Email'), $user->email, 'mail', 'bg-sky-50 text-sky-600 dark:bg-sky-500/10 dark:text-sky-300'],
                    [__('Phone number'), $user->phone_number_formatted ?? '—', 'phone', 'bg-emerald-50 text-emerald-600 dark:bg-emerald-500/10 dark:text-emerald-300'],
                    [__('Role'), $roleLabel, 'shield', $isAdminRole ? 'bg-indigo-50 text-indigo-600 dark:bg-indigo-500/10 dark:text-indigo-300' : 'bg-teal-50 text-teal-600 dark:bg-teal-500/10 dark:text-teal-300'],
                    [__('Account created'), $user->created_at?->format('M j, Y'), 'calendar', 'bg-violet-50 text-violet-600 dark:bg-violet-500/10 dark:text-violet-300'],
                    [$isSelf ? __('Last login before this one') : __('Last login'), $lastLogin ? $lastLogin->diffForHumans() : __('None recorded'), 'clock', 'bg-rose-50 text-rose-600 dark:bg-rose-500/10 dark:text-rose-300'],
                ];
            @endphp
            <dl class="grid gap-x-8 gap-y-5 px-6 py-6 sm:grid-cols-2">
                @foreach ($details as [$label, $value, $icon, $tile])
                    <div class="flex items-start gap-3">
                        <span aria-hidden="true" class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg {{ $tile }}">
                            <x-icon :name="$icon" class="h-[18px] w-[18px]" />
                        </span>
                        <div class="min-w-0">
                            <dt class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ $label }}</dt>
                            <dd class="mt-0.5 truncate text-sm font-medium text-gray-900 dark:text-gray-100" title="{{ $value }}">{{ $value }}</dd>
                        </div>
                    </div>
                @endforeach
            </dl>

            <div class="flex flex-wrap items-center justify-between gap-3 px-6 py-4 border-t border-amber-100 dark:border-amber-500/20 bg-amber-50/60 dark:bg-amber-500/5">
                <div class="flex items-center gap-3">
                    <span aria-hidden="true" class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-amber-100 text-amber-700 dark:bg-amber-500/15 dark:text-amber-300">
                        <x-icon name="key" class="h-[18px] w-[18px]" />
                    </span>
                    <div>
                        <p class="text-sm font-medium text-gray-900 dark:text-gray-100">{{ __('Password') }}</p>
                        <p class="text-sm text-gray-600 dark:text-gray-400">
                            {{ $passwordChangedAt ? __('Last changed :when', ['when' => $passwordChangedAt->diffForHumans()]) : __('No change recorded yet') }}
                        </p>
                    </div>
                </div>
                @can('changePassword', $user)
                    <x-change-password-button :user-id="$isSelf ? null : $user->id" />
                @endcan
            </div>
        @endif
    </section>
</div>
