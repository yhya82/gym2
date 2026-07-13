<?php

namespace Tests\Feature;

use App\Enums\MembershipStatus;
use App\Enums\UserRole;
use App\Exceptions\PaymentExceedsBalanceException;
use App\Exceptions\SubscriptionNotActiveException;
use App\Models\Member;
use App\Models\Payment;
use App\Models\Plan;
use App\Models\User;
use App\Services\PaymentService;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PaymentOverpaymentTest extends TestCase
{
    use RefreshDatabase;

    private function makeSubscriptionWithBalance(string $planPrice, string $amountPaid): array
    {
        $staff = User::factory()->create(['role' => UserRole::Admin]);
        $plan = Plan::factory()->create(['price' => $planPrice]);
        $member = Member::factory()->create();

        $subscription = $member->subscriptions()->create([
            'plan_id' => $plan->id,
            'start_date' => now(),
            'expiry_date' => now()->addDays($plan->duration_days),
            'plan_price' => $planPrice,
            'amount_paid' => $amountPaid,
            'balance' => bcsub($planPrice, $amountPaid, 2),
            'status' => MembershipStatus::Active,
        ]);

        return [$subscription, $staff];
    }

    /**
     * App-level check in PaymentService — the fast, friendly validation
     * path, checked independently of the DB trigger below.
     */
    public function test_payment_service_rejects_an_amount_exceeding_the_remaining_balance(): void
    {
        [$subscription, $staff] = $this->makeSubscriptionWithBalance('100.00', '60.00');

        $this->expectException(PaymentExceedsBalanceException::class);

        app(PaymentService::class)->record($subscription, '50.00', $staff);
    }

    public function test_payment_service_accepts_a_payment_within_the_remaining_balance(): void
    {
        [$subscription, $staff] = $this->makeSubscriptionWithBalance('100.00', '60.00');

        $payment = app(PaymentService::class)->record($subscription, '40.00', $staff);

        $this->assertDatabaseHas('payments', ['id' => $payment->id, 'amount' => '40.00']);
        $this->assertSame('100.00', $subscription->fresh()->amount_paid);
        $this->assertSame('0.00', $subscription->fresh()->balance);
    }

    /**
     * A subscription's period ends the moment it's marked expired (either by
     * the cron or by renewal superseding it) — a top-up belongs to the
     * current period only; a lapsed member must be renewed, not paid against
     * their old, closed-out subscription.
     */
    public function test_payment_service_rejects_a_payment_against_an_expired_subscription(): void
    {
        $staff = User::factory()->create(['role' => UserRole::Admin]);
        $plan = Plan::factory()->create(['price' => '100.00']);
        $member = Member::factory()->create();
        $subscription = $member->subscriptions()->create([
            'plan_id' => $plan->id,
            'start_date' => now()->subDays(40),
            'expiry_date' => now()->subDays(10),
            'plan_price' => '100.00',
            'amount_paid' => '20.00',
            'balance' => '80.00',
            'status' => MembershipStatus::Expired,
        ]);

        $this->expectException(SubscriptionNotActiveException::class);

        app(PaymentService::class)->record($subscription, '50.00', $staff);
    }

    /**
     * The trg_payments_before_insert trigger is the hard, race-safe backstop
     * — tested independently by inserting directly through the Payment
     * model, bypassing PaymentService's app-level check entirely.
     */
    public function test_database_trigger_rejects_overpayment_bypassing_the_app_layer(): void
    {
        [$subscription, $staff] = $this->makeSubscriptionWithBalance('100.00', '60.00');

        $this->expectException(QueryException::class);
        $this->expectExceptionMessage('Payment exceeds remaining balance on subscription');

        Payment::create([
            'member_id' => $subscription->member_id,
            'subscription_id' => $subscription->id,
            'amount' => '50.00',
            'payment_date' => now(),
            'received_by' => $staff->id,
        ]);
    }
}
