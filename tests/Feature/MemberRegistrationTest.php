<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Events\MemberRegistered;
use App\Exceptions\PaymentExceedsBalanceException;
use App\Models\Member;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use App\Services\MemberRegistrationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class MemberRegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_creates_member_subscription_and_payment_together(): void
    {
        Event::fake([MemberRegistered::class]);

        $plan = Plan::factory()->create(['price' => '100.00', 'duration_days' => 30]);
        $staff = User::factory()->create(['role' => UserRole::Admin]);

        $member = app(MemberRegistrationService::class)->register(
            'Jane Doe',
            '+2207001234',
            $plan,
            now(),
            '40.00',
            $staff,
        );

        $this->assertDatabaseHas('members', [
            'id' => $member->id,
            'full_name' => 'Jane Doe',
            'phone_number' => '+2207001234',
        ]);
        $this->assertDatabaseHas('subscriptions', [
            'member_id' => $member->id,
            'plan_price' => '100.00',
            'amount_paid' => '40.00',
            'balance' => '60.00',
        ]);
        $this->assertDatabaseHas('payments', [
            'member_id' => $member->id,
            'amount' => '40.00',
        ]);

        Event::assertDispatched(MemberRegistered::class);
    }

    /**
     * Rule 4: a failed payment must prevent member creation entirely — the
     * member and subscription rows created earlier in the same
     * DB::transaction() must roll back, not just the payment insert.
     */
    public function test_payment_exceeding_plan_price_rolls_back_member_and_subscription(): void
    {
        $plan = Plan::factory()->create(['price' => '100.00']);
        $staff = User::factory()->create(['role' => UserRole::Admin]);

        try {
            app(MemberRegistrationService::class)->register(
                'Jane Doe',
                '+2207001234',
                $plan,
                now(),
                '150.00',
                $staff,
            );
            $this->fail('Expected PaymentExceedsBalanceException was not thrown.');
        } catch (PaymentExceedsBalanceException) {
            // expected
        }

        $this->assertSame(0, Member::count());
        $this->assertSame(0, Subscription::count());
    }
}
