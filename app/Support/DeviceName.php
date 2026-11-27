<?php

namespace App\Support;

/**
 * "Chrome · Windows" from a User-Agent string: enough to tell a person's
 * devices apart, without pulling in a full UA-parsing library. Shared by
 * the Security Center's session list and the Audit Log.
 */
final class DeviceName
{
    public static function from(?string $userAgent): string
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
