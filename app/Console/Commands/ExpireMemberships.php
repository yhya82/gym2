<?php

namespace App\Console\Commands;

use App\Enums\MembershipStatus;
use App\Events\MembershipExpired;
use App\Models\Member;
use App\Models\Subscription;
use App\Services\AuditLogger;
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
    protected $description = 'Flip subscriptions/members on their expiry date to expired and dispatch MembershipExpired events';

    /**
     * Execute the console command.
     *
     * MembershipRenewalService already expires a member's previous
     * subscription the moment they renew, so at most one subscription per
     * member is ever active — this query's status='active' AND expiry_date
     * <= today match is therefore always a genuinely overdue membership,
     * never one superseded by a later renewal.
     */
    public function handle(AuditLogger $audit): int
    {
        $today = now()->toDateString();
        $expiredCount = 0;

        Subscription::query()
            ->where('status', MembershipStatus::Active)
            ->where('expiry_date', '<=', $today)
            ->with(['member', 'plan'])
            ->chunkById(100, function ($subscriptions) use (&$expiredCount, $audit) {
                foreach ($subscriptions as $subscription) {
                    DB::transaction(function () use ($subscription, $audit) {
                        $subscription->update([
                            'status' => MembershipStatus::Expired,
                        ]);

                        // If the member is archived (soft-deleted), the relationship
                        // returns null. In that case, leave the member archived
                        // and only expire the subscription.
                        if ($subscription->member) {
                            Member::withoutEvents(
                                fn () => $subscription->member->update([
                                    'status' => MembershipStatus::Expired,
                                ])
                            );

                            // Nobody did this, so it's attributed to System
                            // (see AuditLogger::system). Inside the
                            // transaction: no entry for an expiry that
                            // rolled back.
                            $plan = $subscription->plan?->plan_name;
                            $audit->system(
                                'expire',
                                'members',
                                "Membership of \"{$subscription->member->full_name}\"".($plan ? " ({$plan})" : '')." expired on {$subscription->expiry_date->toDateString()}.",
                                subject: $subscription->member,
                                changes: ['status' => ['old' => MembershipStatus::Active->value, 'new' => MembershipStatus::Expired->value]],
                            );

                            // afterCommit() guarantees this fires only once this
                            // subscription's transaction actually commits.
                            DB::afterCommit(
                                fn () => MembershipExpired::dispatch($subscription->member)
                            );
                        }
                    });

                    $expiredCount++;
                }
            });

        $this->info("Expired {$expiredCount} membership(s).");

        return self::SUCCESS;
    }
}