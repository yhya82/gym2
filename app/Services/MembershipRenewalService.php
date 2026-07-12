<?php

namespace App\Services;

use App\Enums\MembershipStatus;
use App\Events\MembershipRenewed;
use App\Models\Member;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class MembershipRenewalService
{
    public function __construct(
        private readonly PaymentService $payments,
        private readonly AuditLogger $audit,
    ) {}

    /**
     * Renews a member by creating a brand new subscription row — it never
     * mutates or removes the member's prior subscriptions or payments,
     * per the Renewal Rules ("previous subscriptions/payments remain
     * stored"). The member's own record is untouched aside from its status.
     */
    public function renew(
        Member $member,
        Plan $plan,
        Carbon $startDate,
        string $paymentAmount,
        User $staff,
    ): Subscription {
        return DB::transaction(function () use ($member, $plan, $startDate, $paymentAmount, $staff) {
            // A member's current period ends the moment they renew, so any
            // subscription still marked active is superseded now — not left
            // to linger until its own expiry_date passes. Without this, a
            // member could end up with two "active" subscriptions at once
            // (the stale one and the new one), which would break the expiry
            // cron's simple status='active' AND expiry_date < today scan: it
            // has no way to tell a superseded-by-renewal row from a
            // genuinely-overdue one.
            $member->subscriptions()
                ->where('status', MembershipStatus::Active)
                ->update(['status' => MembershipStatus::Expired]);

            $expiryDate = Carbon::instance($startDate)->addDays($plan->duration_days);

            $subscription = $member->subscriptions()->create([
                'plan_id' => $plan->id,
                'start_date' => $startDate,
                'expiry_date' => $expiryDate,
                'plan_price' => $plan->price,
                'amount_paid' => 0,
                'balance' => $plan->price,
                'status' => MembershipStatus::Active,
            ]);

            $this->payments->record($subscription, $paymentAmount, $staff, $startDate);

            // Suppressed here and logged explicitly below instead: a blind
            // Member "updated" observer can't tell this status flip apart
            // from a genuine profile edit (MemberObserver relies on exactly
            // this call being wrapped in withoutEvents()).
            Member::withoutEvents(fn () => $member->update(['status' => MembershipStatus::Active]));

            $subscription = $subscription->fresh(['payments', 'plan', 'member']);

            $this->audit->log(
                'renew',
                'members',
                "Renewed \"{$member->full_name}\" on {$plan->plan_name} membership until {$subscription->expiry_date->toDateString()}."
            );

            // afterCommit() guarantees this only fires once the *outermost*
            // transaction commits, correct whether renew() is called
            // top-level or nested inside a larger transaction later.
            DB::afterCommit(fn () => MembershipRenewed::dispatch($subscription->member, $subscription));

            return $subscription;
        });
    }
}
