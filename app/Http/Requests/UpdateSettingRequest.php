<?php

namespace App\Http\Requests;

use App\Models\ApplicationSetting;
use Illuminate\Foundation\Http\FormRequest;

class UpdateSettingRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can('update', ApplicationSetting::current());
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'application_name' => ['required', 'string', 'max:255'],
            'logo' => ['nullable', 'string', 'max:255'],
            'location' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            // Format/validity (libphonenumber) is enforced by PhoneNumberService
            // in the controller, not here — same split as Member/User's phone.
            'phone' => ['nullable', 'string', 'max:20'],
            'currency' => ['required', 'string', 'max:3'],
            'timezone' => ['required', 'string', 'timezone'],
            'default_theme' => ['required', 'in:light,dark'],
        ];
    }
}
