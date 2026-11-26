<?php

namespace App\Livewire\Forms;

use App\Models\ApplicationSetting;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
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
     * Lockout policy (Security Center > Password Policy):
     *
     * - A real account is locked for `lockout_minutes` once it reaches
     *   `lockout_attempts` failed logins in a row. The lock lives on the
     *   user row (users.locked_until), so an Admin can lift it from the
     *   Security Center.
     * - An email with no account has no row to lock, so the same limits are
     *   applied per email + IP through the rate limiter instead — otherwise
     *   guessing at non-existent accounts would be unthrottled.
     */
    public function authenticate(): void
    {
        $user = User::where('email', $this->email)->first();

        $this->ensureAccountIsNotLocked($user);

        if (! $user) {
            $this->ensureIsNotRateLimited();
        }

        if (! Auth::attempt($this->only(['email', 'password']), $this->remember)) {
            $user
                ? $this->registerFailedAttempt($user)
                : $this->registerUnknownEmailAttempt();

            throw ValidationException::withMessages([
                'form.email' => trans('auth.failed'),
            ]);
        }

        $this->resetLoginProtection($user);
        $this->checkPasswordAgainstPolicy(Auth::user());
    }

    /**
     * Whether the user must pick a new password before using the app
     * (EnsurePasswordIsCompliant reads this).
     */
    public function mustChangePassword(): bool
    {
        return (bool) Auth::user()?->must_change_password;
    }

    /**
     * Login is the only moment the plain-text password is available
     * without asking for it again, so this is where an existing password
     * is checked against a policy that may have been tightened since it
     * was set. A correct but non-compliant password still logs in — the
     * user just has to replace it before going any further.
     *
     * Also clears the requirement if the policy has since been relaxed
     * enough that the password now passes.
     */
    protected function checkPasswordAgainstPolicy(User $user): void
    {
        $failsPolicy = Validator::make(
            ['password' => $this->password],
            ['password' => [Password::defaults()]],
        )->fails();

        if ($failsPolicy === $user->must_change_password) {
            return;
        }

        $user->forceFill(['must_change_password' => $failsPolicy])->save();

        if ($failsPolicy) {
            app(AuditLogger::class)->log(
                'password-change-required',
                'security',
                "{$user->name}'s password doesn't meet the current password policy; a new one is required.",
                $user->id,
            );
        }
    }

    /**
     * Throws while the account is locked. Once a lock has run out, the
     * failed-attempt counter starts again from zero — otherwise every
     * wrong guess after the first lock would lock the account again
     * immediately.
     */
    protected function ensureAccountIsNotLocked(?User $user): void
    {
        if (! $user || ! $user->locked_until) {
            return;
        }

        if ($user->locked_until->isPast()) {
            $user->forceFill([
                'failed_login_attempts' => 0,
                'locked_until' => null,
            ])->save();

            return;
        }

        event(new Lockout(request()));

        $this->throwLockoutMessage((int) ceil(now()->diffInSeconds($user->locked_until)));
    }

    protected function registerFailedAttempt(User $user): void
    {
        $policy = ApplicationSetting::securityPolicy();
        $attempts = $user->failed_login_attempts + 1;
        $locks = $attempts >= $policy['lockout_attempts'];

        $user->forceFill([
            'failed_login_attempts' => $attempts,
            'locked_until' => $locks ? now()->addMinutes($policy['lockout_minutes']) : null,
        ])->save();

        if ($locks) {
            app(AuditLogger::class)->log(
                'account-locked',
                'security',
                "{$user->name} was locked out for {$policy['lockout_minutes']} minute(s) after {$attempts} failed login attempts.",
                $user->id,
            );
        }
    }

    protected function registerUnknownEmailAttempt(): void
    {
        $policy = ApplicationSetting::securityPolicy();

        RateLimiter::hit($this->throttleKey(), $policy['lockout_minutes'] * 60);
    }

    protected function ensureIsNotRateLimited(): void
    {
        $policy = ApplicationSetting::securityPolicy();

        if (! RateLimiter::tooManyAttempts($this->throttleKey(), $policy['lockout_attempts'])) {
            return;
        }

        event(new Lockout(request()));

        $this->throwLockoutMessage(RateLimiter::availableIn($this->throttleKey()));
    }

    protected function throwLockoutMessage(int $seconds): never
    {
        throw ValidationException::withMessages([
            'form.email' => trans('auth.throttle', [
                'seconds' => $seconds,
                'minutes' => ceil($seconds / 60),
            ]),
        ]);
    }

    protected function resetLoginProtection(?User $user): void
    {
        RateLimiter::clear($this->throttleKey());

        if (! $user || ($user->failed_login_attempts === 0 && $user->locked_until === null)) {
            return;
        }

        $user->forceFill([
            'failed_login_attempts' => 0,
            'locked_until' => null,
        ])->save();
    }

    protected function throttleKey(): string
    {
        return Str::transliterate(Str::lower($this->email).'|'.request()->ip());
    }
}
