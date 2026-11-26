<?php

namespace App\Rules;

use App\Models\ApplicationSetting;
use App\Models\User;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Facades\Hash;

class NotInPasswordHistory implements ValidationRule
{
    /**
     * Rejects a new password that matches the user's current password or
     * one of their last N (Security Center > Password history). This has
     * to be a validation rule rather than a model hook, because validation
     * is the only point where the plain-text password is still available.
     *
     * $user may be null (e.g. a reset for an email with no account), in
     * which case there's no history to check.
     */
    public function __construct(
        private readonly ?User $user,
    ) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $keep = ApplicationSetting::securityPolicy()['password_history'];

        if ($keep === 0 || ! $this->user || ! is_string($value) || $value === '') {
            return;
        }

        // The current password counts as "recently used" too, even for
        // users whose history predates this feature and has no rows yet.
        $hashes = $this->user->passwordHistories()
            ->latest('id')
            ->limit($keep)
            ->pluck('password')
            ->prepend($this->user->password)
            ->unique();

        foreach ($hashes as $hash) {
            if ($hash && Hash::check($value, $hash)) {
                $fail($keep === 1
                    ? 'The :attribute must be different from your current password.'
                    : "The :attribute must not match any of your last {$keep} passwords.");

                return;
            }
        }
    }
}
