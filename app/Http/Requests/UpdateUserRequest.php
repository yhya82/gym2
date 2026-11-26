<?php

namespace App\Http\Requests;

use App\Rules\NotInPasswordHistory;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class UpdateUserRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('user'));
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * Password is optional here — omitted means "leave unchanged."
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
            // Security Center > Password Policy, plus password history:
            // an Admin setting a user's password can't hand back one of
            // that user's recent passwords either.
            'password' => ['nullable', 'string', Password::defaults(), new NotInPasswordHistory($this->route('user'))],
            'role' => ['required', 'in:admin,receptionist'],
        ];
    }
}
