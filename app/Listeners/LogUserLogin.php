<?php

namespace App\Listeners;

use App\Services\AuditLogger;
use Illuminate\Auth\Events\Login;

class LogUserLogin
{
    // Deliberately not ShouldQueue: this needs the current HTTP request's IP
    // address, which a queued job running in a separate process wouldn't have.
    public function __construct(
        private readonly AuditLogger $audit,
    ) {}

    /**
     * Handle the event.
     */
    public function handle(Login $event): void
    {
        $this->audit->log('login', 'auth', "{$event->user->name} logged in.");
    }
}
