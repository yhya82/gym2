<?php

namespace App\Observers;

use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\Auth;

class UserObserver
{
    /**
     * Bookkeeping columns written by login/lockout/session code, never by a
     * person editing an account. Each of those has its own, more precise
     * audit entry where it matters (account-locked, account-unlocked,
     * sessions-revoked, password-change-required), so logging them here as
     * well would only bury the real changes.
     */
    private const IGNORED = [
        'updated_at',
        'remember_token',
        'failed_login_attempts',
        'locked_until',
        'must_change_password',
        // Never put a password hash in the log — password changes get
        // their own entry below.
        'password',
    ];

    public function __construct(
        private readonly AuditLogger $audit,
    ) {}

    public function created(User $user): void
    {
        $this->audit->log('create', 'users', "Created user \"{$user->name}\" ({$user->email}) with role {$user->role?->value}.");
    }

    public function updated(User $user): void
    {
        $self = Auth::id() === $user->id;

        $changes = $this->audit->describeChanges($user, self::IGNORED);

        if ($changes !== '') {
            $who = $self ? "{$user->name} updated their own profile" : "Updated user \"{$user->name}\"";
            $this->audit->log('update', 'users', "{$who} ({$changes}).");
        }

        if ($user->wasChanged('password')) {
            $this->logPasswordChange($user, $self);
        }
    }

    /**
     * Soft delete — "deactivate" in the UI (Admin-only, UserController).
     */
    public function deleted(User $user): void
    {
        $this->audit->log('deactivate', 'users', "Deactivated user \"{$user->name}\" ({$user->email}).");
    }

    public function restored(User $user): void
    {
        $this->audit->log('restore', 'users', "Reactivated user \"{$user->name}\" ({$user->email}).");
    }

    /**
     * Three different situations share one column change:
     *  - an Admin setting someone else's password (Users page),
     *  - a signed-in user changing their own (Profile, or the forced
     *    "Set a new password" screen),
     *  - a password reset from an emailed link, where nobody is signed in —
     *    attributed to the account owner, since AuditLogger would otherwise
     *    skip it for want of a user.
     */
    private function logPasswordChange(User $user, bool $self): void
    {
        if (Auth::check() && ! $self) {
            $this->audit->log('password-set', 'security', 'Set a new password for "'.$user->name.'".');

            return;
        }

        $how = Auth::check() ? 'changed their password' : 'reset their password using an emailed link';

        $this->audit->log('password-changed', 'security', "{$user->name} {$how}.", $user->id);
    }
}
