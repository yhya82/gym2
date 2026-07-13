<?php

namespace App\Livewire;

use App\Models\ApplicationSetting;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Livewire\Component;
use Livewire\WithFileUploads;

class SettingsPage extends Component
{
    use WithFileUploads;

    public string $application_name = '';

    public ?string $logo = null;

    /**
     * The pending upload (Livewire's temporary file), distinct from $logo
     * (the already-stored path) — kept separate so the existing logo stays
     * displayed until a new one is actually saved.
     */
    public mixed $logoUpload = null;

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
            'logoUpload' => ['nullable', 'image', 'max:2048'],
            'location' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:255'],
            'currency' => ['required', 'string', 'max:3'],
            'timezone' => ['required', 'string', 'timezone'],
            'default_theme' => ['required', 'in:light,dark'],
        ]);

        $data = collect($validated)->except('logoUpload')->all();

        if ($this->logoUpload) {
            if ($settings->logo) {
                Storage::disk('public')->delete($settings->logo);
            }

            $data['logo'] = $this->logoUpload->store('logos', 'public');
        }

        $settings->update($data);

        $this->logo = $settings->fresh()->logo;
        $this->logoUpload = null;

        $this->dispatch('settings-saved');
    }

    public function removeLogo(): void
    {
        $settings = ApplicationSetting::current();
        Gate::authorize('update', $settings);

        if ($settings->logo) {
            Storage::disk('public')->delete($settings->logo);
        }

        $settings->update(['logo' => null]);
        $this->logo = null;
        $this->logoUpload = null;
    }

    public function render(): View
    {
        return view('livewire.settings-page');
    }
}
