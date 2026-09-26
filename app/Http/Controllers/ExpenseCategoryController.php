<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreExpenseCategoryRequest;
use App\Http\Requests\UpdateExpenseCategoryRequest;
use App\Models\ExpenseCategory;
use Illuminate\Http\RedirectResponse;

class ExpenseCategoryController extends Controller
{
    // No index of its own — categories are listed on the Expenses page
    // (ExpenseController::index) alongside the expenses they categorize.

    public function store(StoreExpenseCategoryRequest $request): RedirectResponse
    {
        ExpenseCategory::create($request->validated());

        return redirect()->route('expenses.index')->with('status', 'Category created successfully.');
    }

    public function update(UpdateExpenseCategoryRequest $request, ExpenseCategory $expense_category): RedirectResponse
    {
        $expense_category->update($request->validated());

        return redirect()->route('expenses.index')->with('status', 'Category updated successfully.');
    }

    public function destroy(ExpenseCategory $expense_category): RedirectResponse
    {
        $this->authorize('delete', $expense_category);

        $expense_category->delete();

        return redirect()->route('expenses.index')->with('status', "\"{$expense_category->name}\" deleted.");
    }
}
