<?php

namespace Tests\Feature;

use App\Enums\MembershipStatus;
use App\Enums\UserRole;
use App\Livewire\AdminDashboard;
use App\Models\Member;
use App\Models\Plan;
use App\Models\User;
use App\Services\PaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class AdminDashboardTest extends TestCase
{
    use RefreshDatabase;

    private function recordPaymentMonthsAgo(int $monthsAgo, string $amount): void
    {
        $staff = User::factory()->create(['role' => UserRole::Admin]);
        $plan = Plan::factory()->create(['price' => '1000.00']);
        $member = Member::factory()->create();
        $subscription = $member->subscriptions()->create([
            'plan_id' => $plan->id,
            'start_date' => now()->subMonths($monthsAgo),
            'expiry_date' => now()->subMonths($monthsAgo)->addDays($plan->duration_days),
            'plan_price' => $plan->price,
            'amount_paid' => 0,
            'balance' => $plan->price,
            'status' => MembershipStatus::Active,
        ]);

        app(PaymentService::class)->record($subscription, $amount, $staff, now()->subMonths($monthsAgo));
    }

    public function test_the_chart_defaults_to_the_last_six_months(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);

        Livewire::actingAs($admin)
            ->test(AdminDashboard::class)
            ->assertSet('revenueRange', '6')
            ->assertCount('monthlyRevenue', 6);
    }

    public function test_switching_to_three_months_narrows_the_series_and_excludes_older_revenue(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $this->recordPaymentMonthsAgo(0, '100.00');
        $this->recordPaymentMonthsAgo(4, '500.00');

        Livewire::actingAs($admin)
            ->test(AdminDashboard::class)
            ->set('revenueRange', '3')
            ->assertCount('monthlyRevenue', 3);

        // The 4-months-ago payment must not be reachable within the 3-month
        // window's total, regardless of which month label it would fall
        // under — the query itself is scoped by the cutoff date.
        $total = array_sum(
            Livewire::actingAs($admin)->test(AdminDashboard::class)->set('revenueRange', '3')->get('monthlyRevenue')
        );
        $this->assertSame(100.0, $total);
    }

    public function test_switching_to_twelve_months_includes_older_revenue(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $this->recordPaymentMonthsAgo(0, '100.00');
        $this->recordPaymentMonthsAgo(10, '500.00');

        $total = array_sum(
            Livewire::actingAs($admin)->test(AdminDashboard::class)->set('revenueRange', '12')->get('monthlyRevenue')
        );

        $this->assertSame(600.0, $total);
        Livewire::actingAs($admin)
            ->test(AdminDashboard::class)
            ->set('revenueRange', '12')
            ->assertCount('monthlyRevenue', 12);
    }
}
