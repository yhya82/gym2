<?php

namespace App\Http\Requests;

use App\Models\Payment;
use App\Support\MoneyRules;
use Illuminate\Foundation\Http\FormRequest;

class StorePaymentRequest extends FormRequest
{
    /**
     * For recording an additional ("top-up") payment against an existing
     * subscription — separate from the first payment bundled into member
     * registration/renewal, which goes through those flows instead.
     */
    public function authorize(): bool
    {
        return $this->user()->can('create', Payment::class);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * The remaining-balance check happens in PaymentService, not here — it
     * needs a DB row lock, not just static input validation.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'subscription_id' => ['required', 'integer', 'exists:subscriptions,id'],
            'amount' => MoneyRules::positive(),
            'payment_date' => ['nullable', 'date', 'before_or_equal:today'],
        ];
    }
}
