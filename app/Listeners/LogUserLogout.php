<?php

namespace App\Listeners;

use App\Services\AuditLogger;
use Illuminate\Auth\Events\Logout;

class LogUserLogout
{
    // Deliberately not ShouldQueue — same reasoning as LogUserLogin.
    public function __construct(
        private readonly AuditLogger $audit,
    ) {}

    /**
     * Handle the event.
     */
    public function handle(Logout $event): void
    {
        // $event->user can be null in some auth-guard edge cases; nothing to
        // attribute the entry to in that case.
        if (! $event->user) {
            return;
        }

        // Passed explicitly rather than relying on Auth::id(): the session
        // may already be cleared by the time this listener runs, which is
        // exactly why Logout carries its own user reference.
        $this->audit->log('logout', 'auth', "{$event->user->name} logged out.", $event->user->id);
    }
}
