<?php

namespace App\Listeners;

use App\Services\AuditLogger;
use Illuminate\Auth\Events\Failed;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Request;

class LogFailedLogin
{
    // Deliberately not ShouldQueue: needs the current HTTP request's IP.
    public function __construct(
        private readonly AuditLogger $audit,
    ) {}

    /**
     * audit_logs.user_id is NOT NULL with a restrictOnDelete FK — an attempt
     * against an email that matches no account has no valid user_id to
     * attach a row to. A wrong-password attempt against a real account is
     * attributable, so it goes to the searchable audit trail like every
     * other security event; an unknown-email attempt falls back to the
     * application log instead.
     */
    public function handle(Failed $event): void
    {
        $ip = Request::ip();

        if ($event->user) {
            $this->audit->log(
                'failed-login',
                'auth',
                "Failed login attempt for {$event->user->email} from {$ip}.",
                $event->user->id,
            );

            return;
        }

        $email = $event->credentials['email'] ?? 'unknown';
        Log::warning("Failed login attempt for unknown email \"{$email}\" from {$ip}.");
    }
}
