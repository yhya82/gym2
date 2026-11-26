<?php

namespace App\Livewire;

use App\Models\ApplicationSetting;
use App\Models\AuditLog;
use App\Models\LoginAttempt;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\SecurityScore;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class SecurityCenter extends Component
{
    use WithPagination;

    private const TABS = ['overview', 'policy', 'locked', 'sessions', 'events'];

    /** A session counts as "online" if it was active this recently. */
    private const ONLINE_MINUTES = 5;

    /** Audit-log modules shown as security events. */
    private const EVENT_MODULES = ['auth', 'security'];

    #[Url(as: 'tab')]
    public string $tab = 'overview';

    public bool $editing = false;

    // Password policy form — named after their application_settings columns.
    public int|string $password_min_length = 8;

    public bool $password_require_uppercase = false;

    public bool $password_require_lowercase = false;

    public bool $password_require_number = false;

    public bool $password_require_symbol = false;

    public int|string $lockout_attempts = 5;

    public int|string $lockout_minutes = 10;

    public int|string $password_history = 0;

    public int|string $session_timeout_minutes = 120;

    public function mount(): void
    {
        $this->authorizeAdmin();

        if (! in_array($this->tab, self::TABS, true)) {
            $this->tab = 'overview';
        }

        $this->fillPolicyForm();
    }

    public function selectTab(string $tab): void
    {
        $this->tab = in_array($tab, self::TABS, true) ? $tab : 'overview';
    }

    // ---------------------------------------------------------------- policy

    public function startEditing(): void
    {
        $this->authorizeAdmin();
        $this->resetErrorBag();
        $this->fillPolicyForm();
        $this->editing = true;
    }

    public function cancelEditing(): void
    {
        $this->resetErrorBag();
        $this->fillPolicyForm();
        $this->editing = false;
    }

    /**
     * From an Overview recommendation's "Fix" button: opens the policy
     * already in edit mode.
     */
    public function fixPolicy(): void
    {
        $this->tab = 'policy';
        $this->startEditing();
    }

    public function resetToDefaults(): void
    {
        $this->authorizeAdmin();

        foreach (ApplicationSetting::SECURITY_DEFAULTS as $key => $value) {
            $this->{$key} = $value;
        }
    }

    public function savePolicy(): void
    {
        $settings = $this->authorizeAdmin();

        // Same bounds as the CHECK constraints on application_settings.
        $validated = $this->validate([
            'password_min_length' => ['required', 'integer', 'between:6,64'],
            'password_require_uppercase' => ['boolean'],
            'password_require_lowercase' => ['boolean'],
            'password_require_number' => ['boolean'],
            'password_require_symbol' => ['boolean'],
            'lockout_attempts' => ['required', 'integer', 'between:1,20'],
            'lockout_minutes' => ['required', 'integer', 'between:1,1440'],
            'password_history' => ['required', 'integer', 'between:0,24'],
            'session_timeout_minutes' => ['required', 'integer', 'between:5,1440'],
        ], attributes: [
            'password_min_length' => 'minimum password length',
            'lockout_attempts' => 'number of attempts to lockout',
            'lockout_minutes' => 'number of minutes to lockout',
            'password_history' => 'password history',
            'session_timeout_minutes' => 'session timeout',
        ]);

        // ApplicationSettingObserver writes the audit entry.
        $settings->update($validated);

        $this->fillPolicyForm();
        $this->editing = false;
        $this->dispatch('security-policy-saved');
    }

    // ---------------------------------------------------- accounts & sessions

    public function unlock(int $userId): void
    {
        $this->authorizeAdmin();

        $user = User::findOrFail($userId);

        $user->forceFill([
            'failed_login_attempts' => 0,
            'locked_until' => null,
        ])->save();

        app(AuditLogger::class)->log('account-unlocked', 'security', "Unlocked {$user->name}'s account.");
    }

    /**
     * $key is a SHA-256 of the session id, never the id itself: a raw
     * session id is a login token, so it must never be sent to a browser,
     * even an Admin's.
     */
    public function revokeSession(string $key): void
    {
        $this->authorizeAdmin();

        $row = $this->activeSessionRows()->first(fn ($s) => hash('sha256', $s->id) === $key);

        if (! $row) {
            return;
        }

        if ($row->id === session()->getId()) {
            $this->addError('sessions', __('You cannot revoke your own current session. Log out instead.'));

            return;
        }

        $user = User::find($row->user_id);

        DB::table($this->sessionTable())->where('id', $row->id)->delete();

        app(AuditLogger::class)->log(
            'session-revoked',
            'security',
            "Revoked a session for {$user?->name} ({$this->describeDevice($row->user_agent)}, {$row->ip_address}).",
        );
    }

    /**
     * Ends every session the user has (except the Admin's own current one)
     * and rotates their "remember me" token, so a remembered browser can't
     * quietly log them back in.
     */
    public function signOutEverywhere(int $userId): void
    {
        $this->authorizeAdmin();

        $user = User::findOrFail($userId);

        if ($this->usesDatabaseSessions()) {
            DB::table($this->sessionTable())
                ->where('user_id', $user->id)
                ->where('id', '!=', session()->getId())
                ->delete();
        }

        $user->forceFill(['remember_token' => Str::random(60)])->save();

        app(AuditLogger::class)->log('sessions-revoked', 'security', "Signed {$user->name} out of all sessions.");
    }

    // ------------------------------------------------------------- read side

    #[Computed]
    public function policy(): array
    {
        return ApplicationSetting::securityPolicy();
    }

    #[Computed]
    public function score(): array
    {
        return app(SecurityScore::class)->evaluate($this->policy);
    }

    #[Computed]
    public function stats(): array
    {
        $sessions = $this->activeSessionRows();
        $onlineSince = now()->subMinutes(self::ONLINE_MINUTES)->getTimestamp();

        return [
            'total_logins' => LoginAttempt::count(),
            'successful_logins' => LoginAttempt::where('successful', true)->count(),
            'failed_logins' => LoginAttempt::where('successful', false)->count(),
            'locked_accounts' => User::where('locked_until', '>', now())->count(),
            'active_sessions' => $sessions->count(),
            'online_users' => $sessions->where('last_activity', '>=', $onlineSince)->pluck('user_id')->unique()->count(),
        ];
    }

    #[Computed]
    public function lockedUsers(): Collection
    {
        return User::where('locked_until', '>', now())->orderBy('locked_until')->get();
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    #[Computed]
    public function sessions(): Collection
    {
        $rows = $this->activeSessionRows();
        $users = User::whereKey($rows->pluck('user_id')->unique())->get()->keyBy('id');
        $onlineSince = now()->subMinutes(self::ONLINE_MINUTES)->getTimestamp();
        $currentId = session()->getId();

        return $rows
            ->filter(fn ($row) => $users->has($row->user_id))
            ->map(fn ($row) => [
                'key' => hash('sha256', $row->id),
                'user' => $users[$row->user_id],
                'device' => $this->describeDevice($row->user_agent),
                'ip_address' => $row->ip_address,
                'last_active' => Carbon::createFromTimestamp($row->last_activity),
                'online' => $row->last_activity >= $onlineSince,
                'current' => $row->id === $currentId,
            ])
            ->values();
    }

    #[Computed]
    public function recentEvents(): Collection
    {
        return AuditLog::query()
            ->with('user')
            ->whereIn('module', self::EVENT_MODULES)
            ->latest('id')
            ->limit(5)
            ->get();
    }

    public function usesDatabaseSessions(): bool
    {
        return config('session.driver') === 'database';
    }

    /**
     * Badge label + colour classes for an audit-log action.
     *
     * @return array{0: string, 1: string}
     */
    public function eventBadge(string $action): array
    {
        return match ($action) {
            'login' => ['Login', 'bg-green-100 text-green-800 dark:bg-green-500/10 dark:text-green-400'],
            'logout' => ['Logout', 'bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-300'],
            'failed-login' => ['Failed login', 'bg-red-100 text-red-800 dark:bg-red-500/10 dark:text-red-400'],
            'account-locked' => ['Locked', 'bg-amber-100 text-amber-800 dark:bg-amber-500/10 dark:text-amber-400'],
            'account-unlocked' => ['Unlocked', 'bg-indigo-100 text-indigo-800 dark:bg-indigo-500/10 dark:text-indigo-300'],
            'permission-denied' => ['Denied', 'bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-300'],
            'update-policy' => ['Policy', 'bg-indigo-100 text-indigo-800 dark:bg-indigo-500/10 dark:text-indigo-300'],
            'session-revoked', 'sessions-revoked' => ['Session', 'bg-indigo-100 text-indigo-800 dark:bg-indigo-500/10 dark:text-indigo-300'],
            'password-change-required' => ['Must change', 'bg-amber-100 text-amber-800 dark:bg-amber-500/10 dark:text-amber-400'],
            'password-changed' => ['Password', 'bg-green-100 text-green-800 dark:bg-green-500/10 dark:text-green-400'],
            default => [Str::headline($action), 'bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-300'],
        };
    }

    public function render(): View
    {
        $events = $this->tab === 'events'
            ? AuditLog::query()
                ->with('user')
                ->whereIn('module', self::EVENT_MODULES)
                ->latest('id')
                ->paginate(15, pageName: 'eventsPage')
            : null;

        return view('livewire.security-center', compact('events'));
    }

    // --------------------------------------------------------------- helpers

    private function authorizeAdmin(): ApplicationSetting
    {
        $settings = ApplicationSetting::current();
        Gate::authorize('update', $settings);

        return $settings;
    }

    private function fillPolicyForm(): void
    {
        foreach (ApplicationSetting::securityPolicy() as $key => $value) {
            $this->{$key} = $value;
        }
    }

    private function sessionTable(): string
    {
        return config('session.table', 'sessions');
    }

    /**
     * Signed-in sessions that haven't hit the session timeout yet. Sessions
     * only live in a table with the database driver; with any other driver
     * there's nothing to list (the view says so).
     */
    private function activeSessionRows(): Collection
    {
        if (! $this->usesDatabaseSessions()) {
            return collect();
        }

        $since = now()->subMinutes(ApplicationSetting::securityPolicy()['session_timeout_minutes'])->getTimestamp();

        return DB::table($this->sessionTable())
            ->whereNotNull('user_id')
            ->where('last_activity', '>=', $since)
            ->orderByDesc('last_activity')
            ->get(['id', 'user_id', 'ip_address', 'user_agent', 'last_activity']);
    }

    /**
     * "Chrome · Windows" from a user-agent string. Deliberately rough — it
     * only needs to help an Admin tell one session from another.
     */
    private function describeDevice(?string $userAgent): string
    {
        if (! $userAgent) {
            return __('Unknown device');
        }

        $browser = match (true) {
            str_contains($userAgent, 'Edg/') => 'Edge',
            str_contains($userAgent, 'OPR/') => 'Opera',
            str_contains($userAgent, 'Firefox/') => 'Firefox',
            str_contains($userAgent, 'Chrome/') => 'Chrome',
            str_contains($userAgent, 'Safari/') => 'Safari',
            default => __('Browser'),
        };

        $os = match (true) {
            str_contains($userAgent, 'Windows') => 'Windows',
            str_contains($userAgent, 'iPhone'), str_contains($userAgent, 'iPad') => 'iOS',
            str_contains($userAgent, 'Android') => 'Android',
            str_contains($userAgent, 'Mac OS X') => 'macOS',
            str_contains($userAgent, 'Linux') => 'Linux',
            default => __('Unknown OS'),
        };

        return "{$browser} · {$os}";
    }
}
