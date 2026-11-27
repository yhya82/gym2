<?php

use App\Livewire\Forms\LoginForm;
use Illuminate\Support\Facades\Session;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.guest-split')] class extends Component
{
    public LoginForm $form;

    /**
     * Bumped on every failed attempt. The fields are keyed on it (wire:key),
     * so each failure renders them fresh: the red state and shake replay
     * even when the same error repeats, and the eye toggle resets to hidden.
     */
    public int $failures = 0;

    /**
     * Handle an incoming authentication request.
     */
    public function login(): void
    {
        try {
            // Field messages live on LoginForm::messages().
            $this->validate();

            $this->form->authenticate();
        } catch (ValidationException $e) {
            // Any failure clears the password; the email stays as typed.
            $this->form->reset('password');
            $this->failures++;
            $this->setErrorBag($e->validator->errors());

            return;
        }

        Session::regenerate();

        // Straight to the change screen rather than via the intended URL,
        // which EnsurePasswordIsCompliant would only bounce back from.
        if ($this->form->mustChangePassword()) {
            $this->redirectRoute('password.change-required', navigate: true);

            return;
        }

        $this->redirectIntended(default: route('dashboard', absolute: false), navigate: true);
    }
}; ?>

@php
    // Invalid credentials can't say which field was wrong (that would reveal
    // whether the email exists), so both fields are marked; the message sits
    // under the password. A lock is about the account, so it's a banner and
    // neither field is marked.
    $credentialsError = $errors->first('form.credentials');
    $emailInvalid = $errors->has('form.email') || $credentialsError;
    $passwordInvalid = $errors->has('form.password') || $credentialsError;
    $focus = $errors->has('form.email') ? 'email' : (($emailInvalid || $passwordInvalid) ? 'password' : null);

    $inputBase = 'block w-full rounded-md shadow-sm dark:bg-gray-800 dark:text-gray-100';
    $inputOk = 'border-gray-300 dark:border-gray-600 focus:border-indigo-500 focus:ring-indigo-500';
    $inputBad = 'border-red-500 dark:border-red-500 focus:border-red-500 focus:ring-red-500 bg-red-50/40 dark:bg-red-500/5';
@endphp

<div>
    <x-auth-session-status class="mb-4 text-green-600 dark:text-green-400" :status="session('status')" />

    @error('form.locked')
        <div role="alert" class="mb-4 flex items-start gap-3 rounded-md border border-red-200 dark:border-red-500/30 bg-red-50 dark:bg-red-500/10 px-4 py-3 text-sm text-red-800 dark:text-red-300 animate-shake" wire:key="locked-{{ $failures }}">
            <svg aria-hidden="true" class="mt-0.5 h-5 w-5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="4" y="11" width="16" height="10" rx="2"/><path d="M8 11V7a4 4 0 0 1 8 0v4"/></svg>
            <span>{{ $message }}</span>
        </div>
    @enderror

    <form wire:submit="login" class="space-y-4" novalidate>
        {{-- Email --}}
        <div wire:key="email-field-{{ $failures }}"
             x-data="{ invalid: @js((bool) $emailInvalid) }"
             x-init="@js($focus === 'email') && $nextTick(() => $refs.input.focus())">
            <label for="email" class="block text-sm font-medium text-gray-700 dark:text-gray-200">{{ __('Email') }}</label>
            <input
                x-ref="input"
                wire:model="form.email"
                x-on:input="invalid = false"
                id="email"
                type="email"
                name="email"
                required
                @if ($failures === 0) autofocus @endif
                autocomplete="username"
                x-bind:aria-invalid="invalid ? 'true' : 'false'"
                @if ($errors->has('form.email')) aria-describedby="email-error" @endif
                class="mt-1 {{ $inputBase }}"
                x-bind:class="invalid ? @js($inputBad.' animate-shake') : @js($inputOk)"
            >
            @error('form.email')
                <p id="email-error" x-show="invalid" role="alert" class="mt-1.5 flex items-center gap-1.5 text-sm text-red-600 dark:text-red-400">
                    <svg aria-hidden="true" class="h-4 w-4 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><line x1="12" y1="8" x2="12" y2="12.5"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                    {{ $message }}
                </p>
            @enderror
        </div>

        {{-- Password, with show/hide --}}
        <div wire:key="password-field-{{ $failures }}"
             x-data="{ invalid: @js((bool) $passwordInvalid), show: false }"
             x-init="@js($focus === 'password') && $nextTick(() => $refs.input.focus())">
            <label for="password" class="block text-sm font-medium text-gray-700 dark:text-gray-200">{{ __('Password') }}</label>
            <div class="relative mt-1">
                <input
                    x-ref="input"
                    wire:model="form.password"
                    x-on:input="invalid = false"
                    id="password"
                    x-bind:type="show ? 'text' : 'password'"
                    type="password"
                    name="password"
                    required
                    autocomplete="current-password"
                    x-bind:aria-invalid="invalid ? 'true' : 'false'"
                    @if ($errors->has('form.password') || $credentialsError) aria-describedby="password-error" @endif
                    class="{{ $inputBase }} pr-11"
                    x-bind:class="invalid ? @js($inputBad.' animate-shake') : @js($inputOk)"
                >
                <button
                    type="button"
                    x-on:click="show = ! show; $refs.input.focus()"
                    x-bind:aria-label="show ? @js(__('Hide password')) : @js(__('Show password'))"
                    x-bind:aria-pressed="show ? 'true' : 'false'"
                    aria-label="{{ __('Show password') }}"
                    aria-controls="password"
                    class="absolute inset-y-0 right-0 flex w-11 items-center justify-center rounded-r-md text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200 focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500"
                >
                    {{-- Eye (password hidden — click to show) --}}
                    <svg x-show="! show" aria-hidden="true" class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12z"/><circle cx="12" cy="12" r="3"/></svg>
                    {{-- Eye with slash (password shown — click to hide) --}}
                    <svg x-show="show" x-cloak aria-hidden="true" class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M10.6 5.1A10.4 10.4 0 0 1 12 5c6.5 0 10 7 10 7a17.6 17.6 0 0 1-2.9 3.9M6.6 6.6C3.7 8.4 2 12 2 12s3.5 7 10 7a9.7 9.7 0 0 0 5.4-1.6"/><path d="M9.9 9.9a3 3 0 0 0 4.2 4.2"/><line x1="3" y1="3" x2="21" y2="21"/></svg>
                </button>
            </div>
            @if ($message = ($errors->first('form.password') ?: $credentialsError))
                <p id="password-error" x-show="invalid" role="alert" class="mt-1.5 flex items-start gap-1.5 text-sm text-red-600 dark:text-red-400">
                    <svg aria-hidden="true" class="mt-0.5 h-4 w-4 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><line x1="12" y1="8" x2="12" y2="12.5"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                    <span>{{ $message }}</span>
                </p>
            @endif
        </div>

        <div class="flex items-center justify-between">
            <label for="remember" class="inline-flex items-center">
                <input wire:model="form.remember" id="remember" type="checkbox" class="rounded border-gray-300 dark:border-gray-600 dark:bg-gray-800 text-indigo-600 shadow-sm focus:ring-indigo-500" name="remember">
                <span class="ms-2 text-sm text-gray-600 dark:text-gray-400">{{ __('Remember me') }}</span>
            </label>

            @if (Route::has('password.request'))
                <a class="text-sm text-indigo-600 dark:text-indigo-400 hover:underline" href="{{ route('password.request') }}" wire:navigate>
                    {{ __('Forgot your password?') }}
                </a>
            @endif
        </div>

        <button type="submit" wire:loading.attr="disabled" class="w-full inline-flex justify-center items-center px-4 py-2 rounded-md bg-indigo-600 text-white text-sm font-medium hover:bg-indigo-500 disabled:opacity-60">
            <span wire:loading.remove wire:target="login">{{ __('Log in') }}</span>
            <span wire:loading wire:target="login">{{ __('Logging in…') }}</span>
        </button>
    </form>
</div>
