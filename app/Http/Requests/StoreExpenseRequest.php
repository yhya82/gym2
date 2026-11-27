<?php

namespace App\Http\Requests;

use App\Models\Expense;
use App\Support\MoneyRules;
use Illuminate\Foundation\Http\FormRequest;

class StoreExpenseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', Expense::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'expense_category_id' => ['required', 'integer', 'exists:expense_categories,id'],
            'amount' => MoneyRules::positive(),
            'expense_date' => ['required', 'date'],
        ];
    }
}
