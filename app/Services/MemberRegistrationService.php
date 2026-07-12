<?php

namespace App\Services;

use App\Enums\MembershipStatus;
use App\Events\MemberRegistered;
use App\Models\Member;
use App\Models\Plan;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class MemberRegistrationService
{
    public function __construct(
        private readonly PhoneNumberService $phoneNumbers,
        private readonly PaymentService $payments,
    ) {}

    /**
     * Creates a member, its first subscription, and its first payment as one
     * atomic transaction (Rule 5). If the payment fails — whether the
     * amount is invalid, or PaymentService rejects it as exceeding the plan
     * price — the member and subscription are rolled back too (Rule 4: a
     * failed payment must prevent member creation). A member is never
     * created without a payment (Rule 1), since PaymentService itself
     * rejects a zero/negative amount before anything is written.
     */
    public function register(
        string $fullName,
        string $rawPhoneNumber,
        Plan $plan,
        Carbon $startDate,
        string $paymentAmount,
        User $staff,
    ): Member {
        $canonicalPhone = $this->phoneNumbers->canonicalize($rawPhoneNumber);

        return DB::transaction(function () use ($fullName, $canonicalPhone, $plan, $startDate, $paymentAmount, $staff) {
            $member = Member::create([
                'full_name' => $fullName,
                'phone_number' => $canonicalPhone,
                'status' => MembershipStatus::Active,
                'created_by' => $staff->id,
            ]);

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

            $member = $member->fresh(['subscriptions', 'payments']);

            // afterCommit() guarantees this only fires once the *outermost*
            // transaction commits, correct whether register() is called
            // top-level or nested inside a larger transaction later.
            DB::afterCommit(fn () => MemberRegistered::dispatch($member));

            return $member;
        });
    }
}
