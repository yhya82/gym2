<?php

namespace App\Livewire;

use App\Exceptions\InvalidPhoneNumberException;
use App\Models\ApplicationSetting;
use App\Services\PhoneNumberService;
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

    public function save(PhoneNumberService $phoneNumbers): void
    {
        $settings = ApplicationSetting::current();
        Gate::authorize('update', $settings);

        $validated = $this->validate([
            'application_name' => ['required', 'string', 'max:255'],
            'logoUpload' => ['nullable', 'image', 'max:2048'],
            'location' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            // Format/validity (libphonenumber) is enforced by PhoneNumberService
            // below, not here — same split as Member/User's phone.
            'phone' => ['nullable', 'string', 'max:20'],
            'currency' => ['required', 'string', 'max:3'],
            'timezone' => ['required', 'string', 'timezone'],
            'default_theme' => ['required', 'in:light,dark'],
        ]);

        $data = collect($validated)->except('logoUpload')->all();

        // An empty string, not null, is what a cleared Livewire text
        // property actually holds — the column's CHECK constraint only
        // auto-passes on a real NULL, not ''.
        if ($data['phone'] === '') {
            $data['phone'] = null;
        } elseif ($data['phone']) {
            try {
                $data['phone'] = $phoneNumbers->canonicalize($data['phone']);
            } catch (InvalidPhoneNumberException $e) {
                $this->addError('phone', $e->getMessage());

                return;
            }
        }

        if ($this->logoUpload) {
            try {
                $path = $this->logoUpload->store('logos', 's3');

                // Deliberately not exists()/HeadObject: that needs read
                // permission on the R2 token, which is currently broken
                // (confirmed via a live 401 on GetObject) even though writes
                // succeed. temporaryUrl() only signs a request locally — it
                // never contacts R2 — so it still catches a genuine signing/
                // credentials failure without depending on the same broken
                // read path. Weaker than the original check (it can't catch
                // a silently failed write, since the 's3' disk has
                // 'throw' => false), but that's the tradeoff for not
                // blocking every real upload on a permission this app can't
                // fix from code.
                Storage::disk('s3')->temporaryUrl($path, now()->addMinutes(10));
                $uploaded = true;
            } catch (\Throwable $e) {
                $uploaded = false;
            }

            if (! $uploaded) {
                $this->addError('logoUpload', __('The logo failed to upload — please try again.'));

                return;
            }

            if ($settings->logo) {
                Storage::disk('s3')->delete($settings->logo);
            }

            $data['logo'] = $path;
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
            Storage::disk('s3')->delete($settings->logo);
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
