<?php

use App\Models\AuditLog;
use App\Models\LoginAttempt;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Expiry is date-granular (Rule: current date > expiry date), so once a day,
// shortly after midnight, is as precise as the underlying comparison ever is.
Schedule::command('memberships:expire')->everyFiveMinutes();

// 12-month retention for the audit trail and login attempts (see
// AuditLog::RETENTION_MONTHS). Listed explicitly so nothing else that
// becomes prunable later gets swept up by accident.
Schedule::command('model:prune', [
    '--model' => [AuditLog::class, LoginAttempt::class],
])->dailyAt('01:00');
