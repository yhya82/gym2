<?php

namespace App\Support;

use Illuminate\Support\Str;

/**
 * How audit-log entries are presented — one place, shared by the Audit Log
 * page and the Security Center's event feed so the two never disagree.
 *
 * Actions and modules are free-form strings in the table (AuditLogger::log),
 * so anything not listed here still displays: as a headline-cased label in
 * neutral grey.
 */
final class AuditActions
{
    /** Every module the app writes, for the filter dropdown. */
    public const MODULES = [
        'auth' => 'Authentication',
        'security' => 'Security',
        'users' => 'Users',
        'members' => 'Members',
        'plans' => 'Plans',
        'payments' => 'Payments',
        'expenses' => 'Expenses',
        'expense_categories' => 'Expense categories',
        'settings' => 'Settings',
        'audit' => 'Audit log',
    ];

    private const GREEN = 'bg-green-100 text-green-800 dark:bg-green-500/10 dark:text-green-400';

    private const RED = 'bg-red-100 text-red-800 dark:bg-red-500/10 dark:text-red-400';

    private const AMBER = 'bg-amber-100 text-amber-800 dark:bg-amber-500/10 dark:text-amber-400';

    private const INDIGO = 'bg-indigo-100 text-indigo-800 dark:bg-indigo-500/10 dark:text-indigo-300';

    private const GREY = 'bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-300';

    /** action => [label, badge classes] */
    private const ACTIONS = [
        'create' => ['Created', self::GREEN],
        'update' => ['Updated', self::INDIGO],
        'delete' => ['Deleted', self::RED],
        'archive' => ['Archived', self::AMBER],
        'restore' => ['Restored', self::GREEN],
        'renew' => ['Renewed', self::GREEN],
        'deactivate' => ['Deactivated', self::RED],
        'export' => ['Exported', self::AMBER],
        'login' => ['Login', self::GREEN],
        'logout' => ['Logout', self::GREY],
        'failed-login' => ['Failed login', self::RED],
        'permission-denied' => ['Denied', self::GREY],
        'account-locked' => ['Locked', self::AMBER],
        'account-unlocked' => ['Unlocked', self::INDIGO],
        'update-policy' => ['Policy', self::INDIGO],
        'session-revoked' => ['Session', self::INDIGO],
        'sessions-revoked' => ['Session', self::INDIGO],
        'password-change-required' => ['Must change', self::AMBER],
        'password-changed' => ['Password', self::GREEN],
        'password-set' => ['Password set', self::AMBER],
        'password-reset-requested' => ['Reset link', self::GREY],
    ];

    public static function label(string $action): string
    {
        return __(self::ACTIONS[$action][0] ?? Str::headline($action));
    }

    public static function badgeClasses(string $action): string
    {
        return self::ACTIONS[$action][1] ?? self::GREY;
    }

    public static function moduleLabel(string $module): string
    {
        return __(self::MODULES[$module] ?? Str::headline($module));
    }
}
