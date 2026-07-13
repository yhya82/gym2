<?php

namespace App\Http\Requests;

use App\Models\Plan;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePlanRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can('create', Plan::class);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * The unique rule is scoped to non-archived rows to match
     * plans.plan_name_active — the generated column that actually enforces
     * uniqueness only among active plans (see Phase 2 migration). Without
     * this, a duplicate name only surfaces as a raw 500 from the DB's
     * unique index instead of a friendly validation error.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'plan_name' => [
                'required', 'string', 'max:100',
                Rule::unique('plans', 'plan_name')->whereNull('deleted_at'),
            ],
            'duration_days' => ['required', 'integer', 'min:1'],
            'price' => ['required', 'numeric', 'min:0'],
        ];
    }
}
