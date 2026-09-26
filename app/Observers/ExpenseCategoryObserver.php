<?php

namespace App\Observers;

use App\Models\ExpenseCategory;
use App\Services\AuditLogger;

class ExpenseCategoryObserver
{
    public function __construct(
        private readonly AuditLogger $audit,
    ) {}

    public function created(ExpenseCategory $category): void
    {
        $this->audit->log('create', 'expense_categories', "Created expense category \"{$category->name}\".");
    }

    public function updated(ExpenseCategory $category): void
    {
        $changes = $this->audit->describeChanges($category);

        if ($changes === '') {
            return;
        }

        $this->audit->log('update', 'expense_categories', "Updated expense category \"{$category->name}\" ({$changes}).");
    }

    public function deleted(ExpenseCategory $category): void
    {
        $this->audit->log('delete', 'expense_categories', "Deleted expense category \"{$category->name}\".");
    }
}
