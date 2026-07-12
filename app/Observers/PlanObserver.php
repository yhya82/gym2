<?php

namespace App\Observers;

use App\Models\Plan;
use App\Services\AuditLogger;

class PlanObserver
{
    public function __construct(
        private readonly AuditLogger $audit,
    ) {}

    // Plans are only ever touched by an explicit Admin action (§3.1) —
    // unlike Member, nothing in this codebase modifies a Plan row as a
    // side effect of something else, so all three lifecycle events here are
    // unambiguous and need no suppression anywhere.

    public function created(Plan $plan): void
    {
        $this->audit->log('create', 'plans', "Created plan \"{$plan->plan_name}\" ({$plan->duration_days} days, {$plan->price}).");
    }

    public function updated(Plan $plan): void
    {
        $changes = $this->audit->describeChanges($plan);

        if ($changes === '') {
            return;
        }

        $this->audit->log('update', 'plans', "Updated plan \"{$plan->plan_name}\" ({$changes}).");
    }

    public function deleted(Plan $plan): void
    {
        $this->audit->log('delete', 'plans', "Deleted plan \"{$plan->plan_name}\".");
    }
}
