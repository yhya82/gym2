<?php

namespace Tests\Feature;

use App\Enums\MembershipStatus;
use App\Enums\UserRole;
use App\Livewire\RenewalModal;
use App\Models\Member;
use App\Models\Plan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class RenewalModalTest extends TestCase
{
    use RefreshDatabase;

    public function test_opening_the_modal_prefills_the_members_current_plan(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $plan = Plan::factory()->create();
        $member = Member::factory()->create();
        $member->subscriptions()->create([
            'plan_id' => $plan->id,
            'start_date' => now()->subDays(10),
            'expiry_date' => now()->addDays(20),
            'plan_price' => $plan->price,
            'amount_paid' => $plan->price,
            'balance' => 0,
            'status' => MembershipStatus::Active,
        ]);

        Livewire::actingAs($admin)
            ->test(RenewalModal::class)
            ->call('loadMember', $member->id)
            ->assertSet('plan_id', $plan->id);
    }

    /**
     * An archived plan isn't offered in the dropdown at all
     * (getPlansProperty only lists non-archived plans) — carrying its id
     * over would leave the select silently mismatched, so it's left blank
     * for staff to choose deliberately instead.
     */
    public function test_opening_the_modal_does_not_prefill_an_archived_plan(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $plan = Plan::factory()->create();
        $member = Member::factory()->create();
        $member->subscriptions()->create([
            'plan_id' => $plan->id,
            'start_date' => now()->subDays(10),
            'expiry_date' => now()->addDays(20),
            'plan_price' => $plan->price,
            'amount_paid' => $plan->price,
            'balance' => 0,
            'status' => MembershipStatus::Active,
        ]);
        $plan->delete();

        Livewire::actingAs($admin)
            ->test(RenewalModal::class)
            ->call('loadMember', $member->id)
            ->assertSet('plan_id', null);
    }

    public function test_opening_the_modal_for_a_member_with_no_subscription_leaves_plan_blank(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $member = Member::factory()->create();

        Livewire::actingAs($admin)
            ->test(RenewalModal::class)
            ->call('loadMember', $member->id)
            ->assertSet('plan_id', null);
    }
}
