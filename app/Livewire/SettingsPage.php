<?php

namespace App\Livewire;

use App\Models\ApplicationSetting;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;

class SettingsPage extends Component
{
    public string $application_name = '';

    public ?string $logo = null;

    public ?string $location = null;

    public ?string $email = null;

    public ?string $phone = null;

    public string $currency = '';

    public string $timezone = '';

    public string $default_theme = 'light';

    public function mount(): void
    {
        $settings = ApplicationSetting::current();
        Gate::authorize('view', $settings);

        $this->application_name = $settings->application_name;
        $this->logo = $settings->logo;
        $this->location = $settings->location;
        $this->email = $settings->email;
        $this->phone = $settings->phone;
        $this->currency = $settings->currency;
        $this->timezone = $settings->timezone;
        $this->default_theme = $settings->default_theme->value;
    }

    public function save(): void
    {
        $settings = ApplicationSetting::current();
        Gate::authorize('update', $settings);

        $validated = $this->validate([
            'application_name' => ['required', 'string', 'max:255'],
            'logo' => ['nullable', 'string', 'max:255'],
            'location' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:255'],
            'currency' => ['required', 'string', 'max:3'],
            'timezone' => ['required', 'string', 'timezone'],
            'default_theme' => ['required', 'in:light,dark'],
        ]);

        $settings->update($validated);

        $this->dispatch('settings-saved');
    }

    public function render(): View
    {
        return view('livewire.settings-page');
    }
}
