<?php

namespace App\View\Components;

use App\Models\ApplicationSetting;
use Illuminate\View\Component;
use Illuminate\View\View;

/**
 * The current Security Center password policy as a live checklist under a
 * password field: each rule ticks as the user types.
 *
 * `$value` is the Alpine expression holding the typed password — e.g.
 * "$wire.password" inside a Livewire component, or "password" for a plain
 * x-data form. The browser checks only mirror the server's rules
 * (AppServiceProvider::configurePasswordPolicy) for feedback; the server
 * still validates everything on save.
 */
class PasswordRequirements extends Component
{
    /** @var list<array{label: string, test: string}> */
    public array $rules = [];

    public int $history;

    public function __construct(public string $value)
    {
        $policy = ApplicationSetting::securityPolicy();
        $v = "({$value} || '')";

        $this->rules[] = [
            'label' => __('At least :n characters', ['n' => $policy['password_min_length']]),
            'test' => "{$v}.length >= {$policy['password_min_length']}",
        ];

        // Same Unicode classes as the server-side rules.
        foreach ([
            'password_require_uppercase' => [__('One uppercase letter (A–Z)'), '/\p{Lu}/u'],
            'password_require_lowercase' => [__('One lowercase letter (a–z)'), '/\p{Ll}/u'],
            'password_require_number' => [__('One number (0–9)'), '/\p{N}/u'],
            'password_require_symbol' => [__('One symbol (e.g. ! @ # $)'), '/[\p{Z}\p{S}\p{P}]/u'],
        ] as $key => [$label, $regex]) {
            if ($policy[$key]) {
                $this->rules[] = ['label' => $label, 'test' => "{$regex}.test({$v})"];
            }
        }

        $this->history = $policy['password_history'];
    }

    public function render(): View
    {
        return view('components.password-requirements');
    }
}
