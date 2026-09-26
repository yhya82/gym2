<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreExpenseRequest;
use App\Http\Requests\UpdateExpenseRequest;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;
use Illuminate\View\View;

class ExpenseController extends Controller
{
    /**
     * Renders the split Expenses/Categories page — both halves live here
     * since managing an expense's category only makes sense alongside the
     * expense list itself, rather than as two separate pages.
     */
    public function index(): View
    {
        $this->authorize('viewAny', Expense::class);

        $expenses = Expense::query()
            ->search(Request::query('search'))
            ->with('category')
            ->latest('expense_date')
            ->paginate(15)
            ->withQueryString();

        $categories = ExpenseCategory::query()->orderBy('name')->get();

        return view('expenses.index', compact('expenses', 'categories'));
    }

    public function store(StoreExpenseRequest $request): RedirectResponse
    {
        Expense::create([
            ...$request->validated(),
            'created_by' => Auth::id(),
        ]);

        return redirect()->route('expenses.index')->with('status', 'Expense logged successfully.');
    }

    public function update(UpdateExpenseRequest $request, Expense $expense): RedirectResponse
    {
        $expense->update($request->validated());

        return redirect()->route('expenses.index')->with('status', 'Expense updated successfully.');
    }

    public function destroy(Expense $expense): RedirectResponse
    {
        $this->authorize('delete', $expense);

        $expense->delete();

        return redirect()->route('expenses.index')->with('status', 'Expense deleted.');
    }
}
