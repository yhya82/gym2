<?php

namespace App\Providers;

use App\Http\Middleware\EnsurePasswordIsCompliant;
use App\Models\ApplicationSetting;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use Livewire\Livewire;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configurePasswordPolicy();

        // Livewire re-applies these to component updates made from a page
        // whose route had them — without this, a component already open
        // when a user was flagged could still be used.
        Livewire::addPersistentMiddleware([
            EnsurePasswordIsCompliant::class,
        ]);
    }

    /**
     * Password::defaults() is what every password field uses (Admin user
     * management, profile password update, password reset), so wiring the
     * Security Center policy in here covers all of them at once.
     *
     * The closure is lazy: settings are only read when a password is
     * actually validated, never at boot.
     */
    private function configurePasswordPolicy(): void
    {
        Password::defaults(function () {
            $policy = ApplicationSetting::securityPolicy();

            // Uppercase and lowercase are checked separately rather than
            // with mixedCase(), since the policy can require either one alone.
            $characterRules = [];

            if ($policy['password_require_uppercase']) {
                $characterRules[] = function (string $attribute, mixed $value, \Closure $fail) {
                    if (! preg_match('/\p{Lu}/u', (string) $value)) {
                        $fail('The :attribute must contain at least one uppercase letter.');
                    }
                };
            }

            if ($policy['password_require_lowercase']) {
                $characterRules[] = function (string $attribute, mixed $value, \Closure $fail) {
                    if (! preg_match('/\p{Ll}/u', (string) $value)) {
                        $fail('The :attribute must contain at least one lowercase letter.');
                    }
                };
            }

            if ($policy['password_require_number']) {
                $characterRules[] = function (string $attribute, mixed $value, \Closure $fail) {
                    if (! preg_match('/\p{N}/u', (string) $value)) {
                        $fail('The :attribute must contain at least one number.');
                    }
                };
            }

            if ($policy['password_require_symbol']) {
                $characterRules[] = function (string $attribute, mixed $value, \Closure $fail) {
                    // Same character class Laravel's own Password::symbols() uses.
                    if (! preg_match('/[\p{Z}\p{S}\p{P}]/u', (string) $value)) {
                        $fail('The :attribute must contain at least one symbol.');
                    }
                };
            }

            return Password::min($policy['password_min_length'])->rules($characterRules);
        });
    }
}
