<?php

namespace App\Livewire\Forms;

use App\Models\ApplicationSetting;
use App\Models\LoginAttempt;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Validate;
use Livewire\Form;

class LoginForm extends Form
{
    /**
     * Per-IP ceiling across all emails. Set well above what honest typing
     * reaches, because a gym's front desk usually puts every staff member
     * behind one shared public IP.
     */
    public const IP_MAX_FAILURES = 30;

    public const IP_DECAY_MINUTES = 15;

    #[Validate('required|string|email')]
    public string $email = '';

    #[Validate('required|string')]
    public string $password = '';

    /**
     * Field messages. A form object is validated by its own validator,
     * which reads this method — not #[Validate(message: [...])], whose
     * per-rule keys Livewire merges across the whole form (the password's
     * "required" message replaced the email's).
     *
     * Keyed both with and without the "form." prefix the page sees, since
     * the form validator and the page address the same field differently.
     *
     * @return array<string, string>
     */
    protected function messages(): array
    {
        $messages = [
            'email.required' => __('The email field is required.'),
            'email.email' => __('Enter a valid email address.'),
            'password.required' => __('The password field is required.'),
        ];

        foreach ($messages as $key => $message) {
            $messages["form.{$key}"] = $message;
        }

        return $messages;
    }

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
     *
     * Messages (lang/en/auth.php) never reveal whether an email has an
     * account: a failure says "Invalid credentials. N attempts left…" and a
     * lock says the account is locked, identically either way. The "N left"
     * count comes from the per email + IP counter for every email, real or
     * not — counting from the account row only for real accounts would let
     * the message itself answer "does this email exist?".
     *
     * Errors use their own keys so the page can place them: form.credentials
     * under the password field, form.locked as a banner above the form.
     *
     * On top of all that, one IP address gets at most IP_MAX_FAILURES failed
     * logins per IP_DECAY_MINUTES across every email. The per-account and
     * per-email limits can't see someone trying one password against many
     * accounts (each account only ever sees one failure); this can.
     */
    public function authenticate(): void
    {
        $user = User::where('email', $this->email)->first();

        $this->ensureIpIsNotThrottled($user);
        $this->ensureAccountIsNotLocked($user);

        if (! $user) {
            $this->ensureIsNotRateLimited();
        }

        if (! Auth::attempt($this->only(['email', 'password']), $this->remember)) {
            $policy = ApplicationSetting::securityPolicy();

            RateLimiter::hit($this->throttleKey(), $policy['lockout_minutes'] * 60);
            RateLimiter::hit($this->ipThrottleKey(), self::IP_DECAY_MINUTES * 60);

            if ($user && $this->registerFailedAttempt($user)) {
                $this->throwLockedMessage();
            }

            $remaining = $policy['lockout_attempts'] - RateLimiter::attempts($this->throttleKey());

            // An unknown email that just used its last attempt is now
            // throttled — say so exactly as a real account's lock would.
            if (! $user && $remaining <= 0) {
                $this->throwLockedMessage();
            }

            // A real account's lock is decided by its own counter above; this
            // per-IP count can lag behind it (e.g. after an Admin unlocks the
            // account), so it never shows fewer than one attempt left.
            $remaining = max(1, $remaining);

            throw ValidationException::withMessages([
                'form.credentials' => trans_choice('auth.invalid_credentials', $remaining, ['count' => $remaining]),
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
                subject: $user,
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
        $this->recordBlockedAttempt($user);

        $this->throwLockedMessage();
    }

    /**
     * The per-IP ceiling (see authenticate()). Its own message: it's about
     * this device, not any one account, so it reveals nothing about which
     * emails exist.
     */
    protected function ensureIpIsNotThrottled(?User $user): void
    {
        if (! RateLimiter::tooManyAttempts($this->ipThrottleKey(), self::IP_MAX_FAILURES)) {
            return;
        }

        event(new Lockout(request()));
        $this->recordBlockedAttempt($user);

        // Once per throttle window, not per refused attempt.
        if (RateLimiter::attempts($this->ipThrottleKey()) === self::IP_MAX_FAILURES) {
            RateLimiter::hit($this->ipThrottleKey(), self::IP_DECAY_MINUTES * 60);
            Log::warning(sprintf(
                'Login throttled for IP %s after %d failed attempts across all accounts.',
                request()->ip(),
                self::IP_MAX_FAILURES,
            ));
        }

        throw ValidationException::withMessages([
            'form.locked' => trans('auth.ip_throttled'),
        ]);
    }

    /**
     * An attempt refused before the password is even checked (locked
     * account, throttled email or IP) never reaches Auth::attempt, so
     * RecordLoginAttempt doesn't see it. Recording it here keeps the
     * Security Center's failed-login count honest during an attack —
     * exactly when it matters.
     */
    protected function recordBlockedAttempt(?User $user): void
    {
        LoginAttempt::create([
            'user_id' => $user?->id,
            'email' => $this->email,
            'ip_address' => request()->ip(),
            'successful' => false,
        ]);
    }

    /**
     * @return bool whether this attempt locked the account
     */
    protected function registerFailedAttempt(User $user): bool
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
                subject: $user,
            );
        }

        return $locks;
    }

    protected function ensureIsNotRateLimited(): void
    {
        $policy = ApplicationSetting::securityPolicy();

        if (! RateLimiter::tooManyAttempts($this->throttleKey(), $policy['lockout_attempts'])) {
            return;
        }

        event(new Lockout(request()));
        $this->recordBlockedAttempt(null);

        $this->throwLockedMessage();
    }

    /**
     * No time remaining in the message, by design: it would tell an
     * attacker exactly when to resume, and staff can ask an Admin instead.
     */
    protected function throwLockedMessage(): never
    {
        throw ValidationException::withMessages([
            'form.locked' => trans('auth.locked'),
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

    /**
     * Deliberately not cleared by a successful login: one valid account
     * shouldn't reset the counter for whoever is guessing at the others.
     */
    protected function ipThrottleKey(): string
    {
        return 'login-ip|'.request()->ip();
    }
}
