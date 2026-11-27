<?php

use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.guest')] class extends Component
{
    /**
     * Reset-link requests allowed per IP address per window — enough for a
     * mistyped address or two, not enough to flood inboxes. (Laravel's own
     * per-account throttle, auth.passwords.users.throttle, still applies.)
     */
    private const MAX_REQUESTS = 5;

    private const DECAY_MINUTES = 15;

    public string $email = '';

    /**
     * Send a password reset link to the provided email address.
     *
     * Whatever happens — a link sent, no such account, or Laravel's
     * per-account "please wait" throttle — the page shows the same reply.
     * Anything else would tell a stranger which emails have accounts, which
     * the login page is careful never to do.
     */
    public function sendPasswordResetLink(): void
    {
        $this->validate([
            'email' => ['required', 'string', 'email'],
        ], [
            'email.required' => __('The email field is required.'),
            'email.email' => __('Enter a valid email address.'),
        ]);

        $key = 'password-reset|'.request()->ip();

        if (RateLimiter::tooManyAttempts($key, self::MAX_REQUESTS)) {
            $this->addError('email', __('Too many reset requests from this device. Try again later.'));

            return;
        }

        RateLimiter::hit($key, self::DECAY_MINUTES * 60);

        $status = Password::sendResetLink($this->only('email'));

        // Only for a real account: audit_logs needs a user to attribute the
        // entry to.
        if ($status === Password::RESET_LINK_SENT && $user = User::where('email', $this->email)->first()) {
            app(AuditLogger::class)->log(
                'password-reset-requested',
                'security',
                "A password reset link was requested for {$user->email}.",
                $user->id,
            );
        }

        $this->reset('email');

        session()->flash('status', __('If that email address has an account, we have sent a password reset link to it.'));
    }
}; ?>

<div>
    <div class="mb-4 text-sm text-gray-600">
        {{ __('Forgot your password? No problem. Just let us know your email address and we will email you a password reset link that will allow you to choose a new one.') }}
    </div>

    <!-- Session Status -->
    <x-auth-session-status class="mb-4" :status="session('status')" />

    <form wire:submit="sendPasswordResetLink">
        <!-- Email Address -->
        <div>
            <x-input-label for="email" :value="__('Email')" />
            <x-text-input wire:model="email" id="email" class="block mt-1 w-full" type="email" name="email" required autofocus />
            <x-input-error :messages="$errors->get('email')" class="mt-2" />
        </div>

        <div class="flex items-center justify-end mt-4">
            <x-primary-button>
                {{ __('Email Password Reset Link') }}
            </x-primary-button>
        </div>
    </form>
</div>
