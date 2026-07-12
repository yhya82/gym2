<?php

namespace App\Http\Requests;

use App\Models\Member;
use Illuminate\Foundation\Http\FormRequest;

class StoreMemberRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can('create', Member::class);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * Phone format/validity (libphonenumber) is enforced by PhoneNumberService
     * inside MemberRegistrationService, not here — this only checks presence.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'full_name' => ['required', 'string', 'max:255'],
            'phone_number' => ['required', 'string', 'max:20'],
            'plan_id' => ['required', 'integer', 'exists:plans,id'],
            'start_date' => ['required', 'date'],
            'payment_amount' => ['required', 'numeric', 'gt:0'],
        ];
    }
}
