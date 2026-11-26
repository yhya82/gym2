<?php

namespace App\Services;

class SecurityScore
{
    /**
     * Scores a password policy out of 100 and lists what would raise it.
     *
     * Each setting is worth a fixed share of the 100 points, awarded in
     * tiers; the top tier of every setting is the recommended value. The
     * weights favour what most reduces the risk of a staff account being
     * taken over at a shared front desk: password strength first, then how
     * quickly an unattended session closes, then lockout and reuse.
     *
     * @param  array<string, int|bool>  $policy  ApplicationSetting::securityPolicy()
     * @return array{
     *     score: int,
     *     grade: string,
     *     recommendations: list<array{points: int, title: string, detail: string}>,
     *     passing: list<string>,
     * }
     */
    public function evaluate(array $policy): array
    {
        $checks = [
            $this->tiered(
                (int) $policy['password_min_length'], 20, [[12, 20], [10, 14], [8, 10], [0, 4]], higherIsBetter: true,
                title: 'Raise minimum length to 12 characters',
                detail: fn ($v) => "Currently {$v}. Longer passwords are much harder to guess.",
                passing: fn ($v) => "Minimum length of {$v} characters",
            ),
            $this->toggle(
                (bool) $policy['password_require_uppercase'], 6,
                title: 'Require at least one uppercase letter', passing: 'Uppercase letter required',
            ),
            $this->toggle(
                (bool) $policy['password_require_lowercase'], 6,
                title: 'Require at least one lowercase letter', passing: 'Lowercase letter required',
            ),
            $this->toggle(
                (bool) $policy['password_require_number'], 6,
                title: 'Require at least one number', passing: 'Number required',
            ),
            $this->toggle(
                (bool) $policy['password_require_symbol'], 8,
                title: 'Require at least one symbol', passing: 'Symbol required',
            ),
            $this->tiered(
                (int) $policy['session_timeout_minutes'], 18, [[30, 18], [60, 12], [120, 6], [PHP_INT_MAX, 2]], higherIsBetter: false,
                title: 'Shorten session timeout to 30 minutes',
                detail: fn ($v) => "Currently {$v}. Front-desk screens are often left unattended.",
                passing: fn ($v) => "{$v}-minute session timeout",
            ),
            $this->tiered(
                (int) $policy['lockout_attempts'], 12, [[5, 12], [10, 6], [PHP_INT_MAX, 0]], higherIsBetter: false,
                title: 'Lock accounts after 5 failed attempts',
                detail: fn ($v) => "Currently {$v}. Fewer guesses before a lock slows down password guessing.",
                passing: fn ($v) => "Lockout after {$v} attempts",
            ),
            $this->tiered(
                (int) $policy['lockout_minutes'], 10, [[10, 10], [5, 5], [0, 0]], higherIsBetter: true,
                title: 'Lock accounts for at least 10 minutes',
                detail: fn ($v) => "Currently {$v}. Short locks barely slow down repeated guessing.",
                passing: fn ($v) => "{$v}-minute lockout",
            ),
            $this->tiered(
                (int) $policy['password_history'], 14, [[5, 14], [3, 9], [1, 4], [0, 0]], higherIsBetter: true,
                title: 'Increase password history to 5',
                detail: fn ($v) => $v === 0
                    ? 'Currently off. Staff can reuse an old password straight away.'
                    : "Currently {$v}. Stops staff cycling back to an old password.",
                passing: fn ($v) => "Last {$v} passwords can't be reused",
            ),
        ];

        $score = array_sum(array_column($checks, 'earned'));

        $recommendations = collect($checks)
            ->filter(fn ($c) => $c['gain'] > 0)
            ->sortByDesc('gain')
            ->map(fn ($c) => ['points' => $c['gain'], 'title' => $c['title'], 'detail' => $c['detail']])
            ->values()
            ->all();

        $passing = collect($checks)
            ->filter(fn ($c) => $c['gain'] === 0)
            ->pluck('passing')
            ->values()
            ->all();

        return [
            'score' => $score,
            'grade' => match (true) {
                $score >= 85 => 'Strong',
                $score >= 60 => 'Fair',
                default => 'Weak',
            },
            'recommendations' => $recommendations,
            'passing' => $passing,
        ];
    }

    /**
     * @param  list<array{0: int, 1: int}>  $tiers  [threshold, points], best tier first
     */
    private function tiered(int $value, int $max, array $tiers, bool $higherIsBetter, string $title, \Closure $detail, \Closure $passing): array
    {
        $earned = 0;

        foreach ($tiers as [$threshold, $points]) {
            if ($higherIsBetter ? $value >= $threshold : $value <= $threshold) {
                $earned = $points;
                break;
            }
        }

        return [
            'earned' => $earned,
            'gain' => $max - $earned,
            'title' => $title,
            'detail' => $detail($value),
            'passing' => $passing($value),
        ];
    }

    private function toggle(bool $on, int $max, string $title, string $passing): array
    {
        return [
            'earned' => $on ? $max : 0,
            'gain' => $on ? 0 : $max,
            'title' => $title,
            'detail' => 'Currently off.',
            'passing' => $passing,
        ];
    }
}
