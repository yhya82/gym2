<?php

namespace App\Services;

use App\Enums\MembershipStatus;
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

            $member->update(['status' => MembershipStatus::Active]);

            return $subscription->fresh(['payments', 'plan']);
        });
    }
}
