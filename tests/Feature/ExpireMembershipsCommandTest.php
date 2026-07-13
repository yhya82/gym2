<?php

namespace Tests\Feature;

use App\Enums\MembershipStatus;
use App\Events\MembershipExpired;
use App\Models\Member;
use App\Models\Plan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class ExpireMembershipsCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_expires_active_subscriptions_past_their_expiry_date(): void
    {
        Event::fake([MembershipExpired::class]);

        $plan = Plan::factory()->create(['price' => '50.00']);

        $overdueMember = Member::factory()->create();
        $overdueSubscription = $overdueMember->subscriptions()->create([
            'plan_id' => $plan->id,
            'start_date' => now()->subDays(40),
            'expiry_date' => now()->subDay(),
            'plan_price' => $plan->price,
            'amount_paid' => $plan->price,
            'balance' => 0,
            'status' => MembershipStatus::Active,
        ]);

        $currentMember = Member::factory()->create();
        $currentSubscription = $currentMember->subscriptions()->create([
            'plan_id' => $plan->id,
            'start_date' => now()->subDays(5),
            'expiry_date' => now()->addDays(25),
            'plan_price' => $plan->price,
            'amount_paid' => $plan->price,
            'balance' => 0,
            'status' => MembershipStatus::Active,
        ]);

        $this->artisan('memberships:expire')->assertSuccessful();

        $this->assertSame(MembershipStatus::Expired, $overdueSubscription->fresh()->status);
        $this->assertSame(MembershipStatus::Expired, $overdueMember->fresh()->status);

        $this->assertSame(MembershipStatus::Active, $currentSubscription->fresh()->status);
        $this->assertSame(MembershipStatus::Active, $currentMember->fresh()->status);

        Event::assertDispatched(MembershipExpired::class, fn ($event) => $event->member->is($overdueMember));
        Event::assertNotDispatched(MembershipExpired::class, fn ($event) => $event->member->is($currentMember));
    }
}
