<?php

namespace App\Livewire\Forms;

use App\Models\User;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Validate;
use Livewire\Form;

class LoginForm extends Form
{
    #[Validate('required|string|email')]
    public string $email = '';

    #[Validate('required|string')]
    public string $password = '';

    #[Validate('boolean')]
    public bool $remember = false;

    /**
     * Attempt to authenticate the user.
     *
     * Lockout policy:
     *
     * 5 failed attempts  -> 1 minute
     * 10 failed attempts -> 10 minutes
     * 15 failed attempts -> 30 minutes
     */
    public function authenticate(): void
    {
        $user = User::where('email', $this->email)->first();

        // Check whether the account is currently locked.
        $this->ensureAccountIsNotLocked($user);

        // Check whether the IP + email combination is currently rate limited.
        $this->ensureIsNotRateLimited();

        // Attempt authentication.
        if (! Auth::attempt(
            $this->only(['email', 'password']),
            $this->remember
        )) {
            if ($user) {
                $this->registerFailedAttempt($user);
            }

            throw ValidationException::withMessages([
                'form.email' => trans('auth.failed'),
            ]);
        }

        // Successful login.
        $this->resetLoginProtection($user);
    }

    /**
     * Check whether the account is currently locked.
     */
    protected function ensureAccountIsNotLocked(?User $user): void
    {
        if (! $user || ! $user->locked_until) {
            return;
        }

        // Lockout has expired.
        if ($user->locked_until->isPast()) {
            $user->forceFill([
                'locked_until' => null,
            ])->save();

            return;
        }

        $seconds = now()->diffInSeconds($user->locked_until);

        event(new Lockout(request()));

        throw ValidationException::withMessages([
            'form.email' => trans('auth.throttle', [
                'seconds' => $seconds,
                'minutes' => ceil($seconds / 60),
            ]),
        ]);
    }

    /**
     * Register a failed login attempt.
     *
     * The account lockout and IP/email rate limiter
     * are synchronized to the same lockout duration.
     */
    protected function registerFailedAttempt(User $user): void
    {
        $attempts = $user->failed_login_attempts + 1;

        $lockoutMinutes = match (true) {
            $attempts >= 15 => 15,
            $attempts >= 10 => 10,
            $attempts >= 5 => 1,
            default => 0,
        };

        $user->forceFill([
            'failed_login_attempts' => $attempts,

            'locked_until' => $lockoutMinutes > 0
                ? now()->addMinutes($lockoutMinutes)
                : null,
        ])->save();

        /*
         * Synchronize Laravel's IP + email rate limiter
         * with the account lockout.
         */
        if ($lockoutMinutes > 0) {
            RateLimiter::clear($this->throttleKey());

            RateLimiter::hit(
                $this->throttleKey(),
                $lockoutMinutes * 60
            );
        }
    }

    /**
     * Check whether the IP + email combination is rate limited.
     */
    protected function ensureIsNotRateLimited(): void
    {
        if (! RateLimiter::tooManyAttempts(
            $this->throttleKey(),
            1
        )) {
            return;
        }

        event(new Lockout(request()));

        $seconds = RateLimiter::availableIn(
            $this->throttleKey()
        );

        throw ValidationException::withMessages([
            'form.email' => trans('auth.throttle', [
                'seconds' => $seconds,
                'minutes' => ceil($seconds / 60),
            ]),
        ]);
    }

    /**
     * Reset all login protection after successful authentication.
     */
    protected function resetLoginProtection(?User $user): void
    {
        RateLimiter::clear($this->throttleKey());

        if (! $user) {
            return;
        }

        $user->forceFill([
            'failed_login_attempts' => 0,
            'locked_until' => null,
        ])->save();
    }

    /**
     * Get the authentication rate limiting throttle key.
     */
    protected function throttleKey(): string
    {
        return Str::transliterate(
            Str::lower($this->email) . '|' . request()->ip()
        );
    }
}