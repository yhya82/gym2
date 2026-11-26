<?php

use App\Models\ApplicationSetting;
use App\Rules\NotInPasswordHistory;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

/*
 * Where EnsurePasswordIsCompliant holds a user whose password failed the
 * current Security Center policy at login. They stay signed in, but can't
 * reach any other page until they set a password that passes.
 */
new #[Layout('layouts.guest')] class extends Component
{
    public string $current_password = '';
    public string $password = '';
    public string $password_confirmation = '';

    public function mount(): void
    {
        if (! Auth::user()->must_change_password) {
            $this->redirectRoute('dashboard', navigate: true);
        }
    }

    public function with(): array
    {
        $policy = ApplicationSetting::securityPolicy();

        $requirements = [__('At least :n characters', ['n' => $policy['password_min_length']])];

        foreach ([
            'password_require_uppercase' => __('One uppercase letter (A–Z)'),
            'password_require_lowercase' => __('One lowercase letter (a–z)'),
            'password_require_number' => __('One number (0–9)'),
            'password_require_symbol' => __('One symbol (e.g. ! @ # $)'),
        ] as $key => $label) {
            if ($policy[$key]) {
                $requirements[] = $label;
            }
        }

        if ($policy['password_history'] > 0) {
            $requirements[] = trans_choice(
                'Different from your current password|Not one of your last :count passwords',
                $policy['password_history'],
            );
        }

        return ['requirements' => $requirements];
    }

    public function updatePassword(): void
    {
        $user = Auth::user();

        try {
            $validated = $this->validate([
                'current_password' => ['required', 'string', 'current_password'],
                'password' => ['required', 'string', 'confirmed', Password::defaults(), new NotInPasswordHistory($user)],
            ]);
        } catch (ValidationException $e) {
            $this->reset('current_password', 'password', 'password_confirmation');

            throw $e;
        }

        // The User model's updated hook clears must_change_password, and
        // UserObserver writes the "password-changed" audit entry.
        $user->update(['password' => Hash::make($validated['password'])]);

        $this->redirectIntended(default: route('dashboard', absolute: false), navigate: true);
    }
}; ?>

<div>
    <h1 class="text-lg font-semibold text-gray-900 dark:text-gray-100">{{ __('Set a new password') }}</h1>
    <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
        {{ __('Your password no longer meets the password policy. Choose a new one to continue.') }}
    </p>

    <div class="mt-4 rounded-md border border-indigo-100 dark:border-indigo-500/20 bg-indigo-50 dark:bg-indigo-500/10 px-4 py-3">
        <p class="text-sm font-medium text-indigo-900 dark:text-indigo-200">{{ __('Your new password must have:') }}</p>
        <ul class="mt-1.5 space-y-1 text-sm text-indigo-800 dark:text-indigo-300">
            @foreach ($requirements as $requirement)
                <li class="flex gap-2"><span aria-hidden="true">•</span>{{ $requirement }}</li>
            @endforeach
        </ul>
    </div>

    <form wire:submit="updatePassword" class="mt-6 space-y-4">
        <div>
            <x-input-label for="current_password" :value="__('Current password')" />
            <x-text-input wire:model="current_password" id="current_password" type="password" class="mt-1 block w-full" autocomplete="current-password" required autofocus />
            <x-input-error :messages="$errors->get('current_password')" class="mt-2" />
        </div>

        <div>
            <x-input-label for="password" :value="__('New password')" />
            <x-text-input wire:model="password" id="password" type="password" class="mt-1 block w-full" autocomplete="new-password" required />
            <x-input-error :messages="$errors->get('password')" class="mt-2" />
        </div>

        <div>
            <x-input-label for="password_confirmation" :value="__('Confirm new password')" />
            <x-text-input wire:model="password_confirmation" id="password_confirmation" type="password" class="mt-1 block w-full" autocomplete="new-password" required />
        </div>

        <x-primary-button class="w-full justify-center">{{ __('Save and continue') }}</x-primary-button>
    </form>

    <form method="POST" action="{{ route('logout') }}" class="mt-4 text-center">
        @csrf
        <button type="submit" class="text-sm text-gray-600 dark:text-gray-400 underline hover:text-gray-900 dark:hover:text-gray-100">{{ __('Log out instead') }}</button>
    </form>
</div>
