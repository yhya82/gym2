<?php

namespace App\Console\Commands;

use App\Enums\MembershipStatus;
use App\Events\MembershipExpired;
use App\Models\Member;
use App\Models\Subscription;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ExpireMemberships extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'memberships:expire';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Flip subscriptions/members past their expiry date to expired and dispatch MembershipExpired events';

    /**
     * Execute the console command.
     *
     * MembershipRenewalService already expires a member's previous
     * subscription the moment they renew, so at most one subscription per
     * member is ever active — this query's status='active' AND expiry_date
     * < today match is therefore always a genuinely overdue membership, never
     * one superseded by a later renewal.
     */
    public function handle(): int
    {
        $today = now()->toDateString();
        $expiredCount = 0;

        Subscription::query()
            ->where('status', MembershipStatus::Active)
            ->where('expiry_date', '<', $today)
            ->with('member')
            ->chunkById(100, function ($subscriptions) use (&$expiredCount) {
                foreach ($subscriptions as $subscription) {
                    DB::transaction(function () use ($subscription) {
                        $subscription->update(['status' => MembershipStatus::Expired]);

                        // Suppressed: "membership expired" isn't in §19.1's
                        // list of actions that must be audit-logged, and this
                        // status flip shouldn't be misread by MemberObserver
                        // as a generic edit if this command is ever triggered
                        // under an authenticated context in the future (e.g.
                        // an admin "run expiry now" action).
                        Member::withoutEvents(
                            fn () => $subscription->member->update(['status' => MembershipStatus::Expired])
                        );

                        // afterCommit() guarantees this fires only once this
                        // subscription's transaction actually commits.
                        DB::afterCommit(fn () => MembershipExpired::dispatch($subscription->member));
                    });

                    $expiredCount++;
                }
            });

        $this->info("Expired {$expiredCount} membership(s).");

        return self::SUCCESS;
    }
}
