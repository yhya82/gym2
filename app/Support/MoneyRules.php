<?php

namespace App\Support;

/**
 * Validation for every money field (payments, plan prices, expenses), kept
 * in one place so they all agree with the database's decimal(10,2) columns.
 *
 * - decimal:0,2 rather than numeric: "numeric" also accepts "1e2" (which
 *   crashed PaymentService's bcmath) and "10.005" (stored as 10.01 on the
 *   payment but added as 10.00 to the subscription, so the two drifted).
 * - max 99,999,999.99: the most a decimal(10,2) column holds. Anything above
 *   used to reach the database and fail with a 500.
 */
final class MoneyRules
{
    public const MAX = '99999999.99';

    /** A required amount greater than zero (payments, expenses). */
    public static function positive(): array
    {
        return ['required', 'decimal:0,2', 'gt:0', 'max:'.self::MAX];
    }

    /** A required amount of zero or more (plan prices — a free plan is allowed). */
    public static function nonNegative(): array
    {
        return ['required', 'decimal:0,2', 'min:0', 'max:'.self::MAX];
    }
}
