<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Expiry is date-granular (Rule: current date > expiry date), so once a day,
// shortly after midnight, is as precise as the underlying comparison ever is.
Schedule::command('memberships:expire')->dailyAt('00:05');
