<?php

namespace App\Listeners;

use App\Models\LoginAttempt;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Support\Facades\Request;

class RecordLoginAttempt
{
    // Not ShouldQueue, same as LogUserLogin/LogFailedLogin: it needs the
    // current request's IP address.

    /**
     * Successful logins. The audit log already has these (LogUserLogin);
     * this table exists so successful and failed attempts can be counted
     * side by side, including failures that have no account behind them.
     */
    public function handleLogin(Login $event): void
    {
        LoginAttempt::create([
            'user_id' => $event->user->getAuthIdentifier(),
            'email' => $event->user->email ?? '',
            'ip_address' => Request::ip(),
            'successful' => true,
        ]);
    }

    /**
     * Failed logins, including attempts against an email that matches no
     * account — the case audit_logs can't record (its user_id is required).
     */
    public function handleFailed(Failed $event): void
    {
        LoginAttempt::create([
            'user_id' => $event->user?->getAuthIdentifier(),
            'email' => (string) ($event->credentials['email'] ?? ''),
            'ip_address' => Request::ip(),
            'successful' => false,
        ]);
    }
}
