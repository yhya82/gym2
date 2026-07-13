<?php

namespace Tests\Feature;

use App\Enums\MembershipStatus;
use App\Enums\UserRole;
use App\Events\MembershipRenewed;
use App\Models\Member;
use App\Models\Plan;
use App\Models\User;
use App\Services\MembershipRenewalService;
use App\Services\PaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class MembershipRenewalTest extends TestCase
{
    use RefreshDatabase;

    public function test_renewal_creates_a_new_subscription_and_expires_the_previous_one(): void
    {
        Event::fake([MembershipRenewed::class]);

        $staff = User::factory()->create(['role' => UserRole::Admin]);
        $originalPlan = Plan::factory()->create(['price' => '50.00', 'duration_days' => 30]);
        $newPlan = Plan::factory()->create(['price' => '80.00', 'duration_days' => 30]);

        $member = Member::factory()->create();
        $originalSubscription = $member->subscriptions()->create([
            'plan_id' => $originalPlan->id,
            'start_date' => now()->subDays(35),
            'expiry_date' => now()->subDays(5),
            'plan_price' => $originalPlan->price,
            'amount_paid' => $originalPlan->price,
            'balance' => 0,
            'status' => MembershipStatus::Active,
        ]);

        $newSubscription = app(MembershipRenewalService::class)->renew(
            $member,
            $newPlan,
            now(),
            '30.00',
            $staff,
        );

        $this->assertNotSame($originalSubscription->id, $newSubscription->id);
        $this->assertSame(MembershipStatus::Expired, $originalSubscription->fresh()->status);
        $this->assertSame(MembershipStatus::Active, $newSubscription->fresh()->status);
        $this->assertSame(MembershipStatus::Active, $member->fresh()->status);

        Event::assertDispatched(MembershipRenewed::class);
    }

    /**
     * Renewal Rules: previous subscriptions and payments must remain stored
     * untouched — renewal only ever creates a new row, never mutates or
     * deletes history.
     */
    public function test_renewal_preserves_prior_subscriptions_and_payments(): void
    {
        $staff = User::factory()->create(['role' => UserRole::Admin]);
        $originalPlan = Plan::factory()->create(['price' => '50.00', 'duration_days' => 30]);
        $newPlan = Plan::factory()->create(['price' => '80.00', 'duration_days' => 30]);

        $member = Member::factory()->create();
        $originalSubscription = $member->subscriptions()->create([
            'plan_id' => $originalPlan->id,
            'start_date' => now()->subDays(35),
            'expiry_date' => now()->subDays(5),
            'plan_price' => $originalPlan->price,
            'amount_paid' => 0,
            'balance' => $originalPlan->price,
            'status' => MembershipStatus::Active,
        ]);
        // Recorded via PaymentService (not a raw insert) so amount_paid/
        // balance stay in sync the same way they would in real use.
        $originalPayment = app(PaymentService::class)->record(
            $originalSubscription,
            (string) $originalPlan->price,
            $staff,
            now()->subDays(35),
        );

        app(MembershipRenewalService::class)->renew($member, $newPlan, now(), '30.00', $staff);

        $this->assertDatabaseHas('subscriptions', [
            'id' => $originalSubscription->id,
            'plan_id' => $originalPlan->id,
            'amount_paid' => $originalSubscription->fresh()->amount_paid,
        ]);
        $this->assertDatabaseHas('payments', [
            'id' => $originalPayment->id,
            'amount' => $originalPayment->amount,
        ]);
        $this->assertSame(2, $member->subscriptions()->count());
    }
}
