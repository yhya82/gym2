<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateUserRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('user'));
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Enter a name.',
            'email.required' => 'Enter an email address.',
            'phone_number.required' => 'Enter a phone number.',
        ];
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * No password here: passwords only change through the Change password
     * modal (App\Livewire\ChangePasswordModal), so editing someone's details
     * can never change their password by accident.
     *
     * Role is "sometimes": the form disables the select when the role can't
     * be changed (UserPolicy::changeRole), and a disabled field isn't
     * submitted at all.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required', 'email', 'max:255',
                Rule::unique('users', 'email')->whereNull('deleted_at')->ignore($this->route('user')),
            ],
            'phone_number' => ['required', 'string', 'max:20'],
            'role' => ['sometimes', 'required', 'in:admin,receptionist'],
        ];
    }
}
