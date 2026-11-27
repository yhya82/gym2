<?php

namespace App\Livewire;

use App\Exceptions\InvalidPhoneNumberException;
use App\Models\ApplicationSetting;
use App\Services\PhoneNumberService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
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
            // Raster images only, by extension AND content: 'image' alone
            // would also accept bmp/gif, and SVG is excluded either way (it
            // can carry script). 2 MB and 2000px are far above what a
            // sidebar logo needs.
            'logoUpload' => ['nullable', 'image', 'mimes:png,jpg,jpeg,webp', 'max:2048', 'dimensions:min_width=32,min_height=32,max_width=2000,max_height=2000'],
            'location' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            // Format/validity (libphonenumber) is enforced by PhoneNumberService
            // below, not here — same split as Member/User's phone.
            'phone' => ['nullable', 'string', 'max:20'],
            'currency' => ['required', 'string', 'max:3'],
            'timezone' => ['required', 'string', 'timezone'],
            'default_theme' => ['required', 'in:light,dark'],
        ], [
            'logoUpload.image' => __('The logo must be an image.'),
            'logoUpload.mimes' => __('The logo must be a PNG, JPG or WebP image.'),
            'logoUpload.max' => __('The logo must be 2 MB or smaller.'),
            'logoUpload.dimensions' => __('The logo must be between 32×32 and 2000×2000 pixels.'),
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

        $previousLogo = $settings->logo;

        if ($this->logoUpload) {
            $path = false;

            try {
                // logos/<random 40-char name>.<extension from the detected
                // content type> — never the uploader's own filename.
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
                //
                // store() itself returns false instead of throwing when the
                // disk has 'throw' => false — that has to be checked
                // explicitly, or `false` would be saved as the logo path.
                if ($path !== false) {
                    Storage::disk('s3')->temporaryUrl($path, now()->addMinutes(10));
                }
            } catch (\Throwable $e) {
                // Usually configuration (missing AWS_* keys or AWS_ENDPOINT
                // for R2) — logged so it can be diagnosed; the Admin just gets
                // a plain message.
                Log::error('Logo upload failed: '.$e->getMessage(), ['exception' => $e]);
                $path = false;
            }

            if ($path === false) {
                $this->addError('logoUpload', __("The logo couldn't be saved. Try again, and if it keeps failing, check the file storage settings."));

                return;
            }

            $data['logo'] = $path;
        }

        $settings->update($data);

        // Only once the new path is saved: deleting first would leave the
        // settings pointing at a file that no longer exists if the save
        // failed.
        if ($this->logoUpload && $previousLogo && $previousLogo !== $data['logo']) {
            Storage::disk('s3')->delete($previousLogo);
        }

        $this->logo = $settings->fresh()->logo;
        $this->logoUpload = null;

        $this->dispatch('settings-saved');
    }

    public function removeLogo(): void
    {
        $settings = ApplicationSetting::current();
        Gate::authorize('update', $settings);

        $previousLogo = $settings->logo;

        $settings->update(['logo' => null]);

        if ($previousLogo) {
            Storage::disk('s3')->delete($previousLogo);
        }

        $this->logo = null;
        $this->logoUpload = null;
    }

    public function render(): View
    {
        return view('livewire.settings-page');
    }
}
