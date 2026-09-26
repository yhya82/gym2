<?php

namespace App\Events;

use Illuminate\Foundation\Events\Dispatchable;

class ExpenseRecorded
{
    use Dispatchable;

    // Plain, non-broadcasting event, mirroring PaymentRecorded — a dedicated
    // broadcast listener reacts to this to push the expense dashboard stats
    // live. Fired on create, update, and delete alike, since any of the
    // three can change the totals the dashboard card shows.
}
