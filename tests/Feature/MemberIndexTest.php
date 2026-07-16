<?php

namespace Tests\Feature;

use App\Enums\MembershipStatus;
use App\Enums\UserRole;
use App\Livewire\MemberIndex;
use App\Models\Member;
use App\Models\Plan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class MemberIndexTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_table_shows_the_current_subscriptions_start_date(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $plan = Plan::factory()->create();
        $member = Member::factory()->create();
        $member->subscriptions()->create([
            'plan_id' => $plan->id,
            'start_date' => '2026-03-05',
            'expiry_date' => now()->addDays(30),
            'plan_price' => $plan->price,
            'amount_paid' => $plan->price,
            'balance' => 0,
            'status' => MembershipStatus::Active,
        ]);

        Livewire::actingAs($admin)
            ->test(MemberIndex::class)
            ->assertSee('Start Date')
            ->assertSee('Mar 5, 2026');
    }
}
