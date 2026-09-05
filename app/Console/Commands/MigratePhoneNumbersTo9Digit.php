<?php

namespace App\Console\Commands;

use App\Models\Member;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\PhoneNumberService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class MigratePhoneNumbersTo9Digit extends Command
{
    protected $signature = 'members:migrate-phone-numbers
        {--dry-run : Preview changes without writing}
        {--user= : ID of the user to attribute the audit log entries to}';

    protected $description = 'One-off: remaps existing 7-digit GM phone numbers to the new 9-digit format.';

    /**
     * Leading digit of the old 7-digit number => 2-digit prefix for the new
     * 9-digit number. '4' and '9' are deliberately absent — any row with
     * one of those leading digits is skipped and reported, not guessed at.
     */
    private const PREFIX_MAP = [
        '3' => '83', '5' => '83',
        '6' => '86', '8' => '86',
        '2' => '87', '7' => '87',
    ];

    public function handle(PhoneNumberService $phoneNumbers, AuditLogger $audit): int
    {
        $dryRun = $this->option('dry-run');

        // AuditLogger::log() silently no-ops without an authenticated user,
        // which a console command never has — so the actor has to be passed
        // in explicitly rather than relying on the implicit Auth::id() the
        // MemberObserver normally uses for a web-triggered update.
        $actorId = null;

        if (! $dryRun) {
            $actorId = $this->option('user');

            if (! $actorId || ! User::whereKey($actorId)->exists()) {
                $this->error('Pass --user=<id> naming an existing user to attribute the audit log entries to.');

                return self::FAILURE;
            }
        }

        $changes = [];
        $skipped = [];

        foreach (Member::withTrashed()->lazy() as $member) {
            $old = $member->phone_number;

            if (! str_starts_with($old, '+220') || strlen($old) !== 11) {
                continue; // already 9-digit, or not a GM number
            }

            $local = substr($old, 4);
            $prefix = self::PREFIX_MAP[$local[0]] ?? null;

            if ($prefix === null) {
                $skipped[] = "member #{$member->id}: {$old} (leading digit '{$local[0]}' has no mapped prefix)";
                continue;
            }

            try {
                $new = $phoneNumbers->canonicalize($prefix.$local);
            } catch (\Throwable $e) {
                $skipped[] = "member #{$member->id}: {$old} failed re-validation: {$e->getMessage()}";
                continue;
            }

            $changes[] = [$member, $old, $new];
        }

        $newNumbers = array_column($changes, 2);
        if (count($newNumbers) !== count(array_unique($newNumbers))) {
            $this->error('Duplicate new numbers detected — aborting, nothing written.');
            return self::FAILURE;
        }

        $this->info(count($changes).' number(s) to update:');
        foreach ($changes as [$member, $old, $new]) {
            $this->line("  #{$member->id}: {$old} -> {$new}");
        }

        if ($skipped) {
            $this->warn(count($skipped).' row(s) skipped:');
            foreach ($skipped as $line) {
                $this->warn("  {$line}");
            }
        }

        if ($dryRun) {
            $this->info('Dry run — no changes written.');
            return self::SUCCESS;
        }

        DB::transaction(function () use ($changes, $audit, $actorId) {
            foreach ($changes as [$member, $old, $new]) {
                $member->update(['phone_number' => $new]);

                $audit->log(
                    'update',
                    'members',
                    "Updated member \"{$member->full_name}\" (phone_number: {$old} → {$new}).",
                    $actorId,
                );
            }
        });

        $this->info(count($changes).' member(s) updated.');
        return self::SUCCESS;
    }
}
