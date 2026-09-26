<?php

namespace App\Observers;

use App\Events\ExpenseRecorded;
use App\Models\Expense;
use App\Services\AuditLogger;

class ExpenseObserver
{
    public function __construct(
        private readonly AuditLogger $audit,
    ) {}

    public function created(Expense $expense): void
    {
        $this->audit->log('create', 'expenses', "Logged expense of {$expense->amount} in category \"{$expense->category->name}\".");

        ExpenseRecorded::dispatch();
    }

    public function updated(Expense $expense): void
    {
        $changes = $this->audit->describeChanges($expense);

        if ($changes !== '') {
            $this->audit->log('update', 'expenses', "Updated expense of {$expense->amount} ({$changes}).");
        }

        ExpenseRecorded::dispatch();
    }

    public function deleted(Expense $expense): void
    {
        $this->audit->log('delete', 'expenses', "Deleted expense of {$expense->amount} in category \"{$expense->category->name}\".");

        ExpenseRecorded::dispatch();
    }
}
