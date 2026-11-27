<?php

namespace Tests\Feature;

use App\Enums\MembershipStatus;
use App\Enums\UserRole;
use App\Livewire\AdminDashboard;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Member;
use App\Models\Payment;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Tests\TestCase;

class DashboardRevenueCardTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Subscription $subscription;

    protected function setUp(): void
    {
        parent::setUp();

        // Fri 27 Nov 2026 — every comparison window below is relative to this.
        Carbon::setTestNow('2026-11-27 10:00:00');

        $this->admin = User::factory()->create(['role' => UserRole::Admin]);
        $member = Member::factory()->create();
        $this->subscription = $member->subscriptions()->create([
            'plan_id' => Plan::factory()->create(['price' => '10000000.00'])->id,
            'start_date' => '2020-01-01',
            'expiry_date' => '2030-01-01',
            'plan_price' => '10000000.00',
            'amount_paid' => 0,
            'balance' => '10000000.00',
            'status' => MembershipStatus::Active,
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function pay(string $date, string $amount): void
    {
        Payment::create([
            'member_id' => $this->subscription->member_id,
            'subscription_id' => $this->subscription->id,
            'amount' => $amount,
            'payment_date' => $date,
            'received_by' => $this->admin->id,
        ]);
    }

    private function card(string $period = 'monthly'): array
    {
        return Livewire::actingAs($this->admin)
            ->test(AdminDashboard::class)
            ->call('setRevenuePeriod', $period)
            ->instance()
            ->revenueCard;
    }

    public function test_monthly_compares_month_to_date_with_the_same_days_last_month(): void
    {
        $this->pay('2026-11-03', '30000.00');
        $this->pay('2026-11-27', '18350.00');   // Nov 1–27: 48,350
        $this->pay('2026-10-10', '43010.00');   // Oct 1–27: 43,010
        $this->pay('2026-10-30', '99999.00');   // after the 27th — not compared

        $card = $this->card('monthly');

        $this->assertSame('This month · 1–27 Nov', $card['label']);
        $this->assertSame(48350.0, $card['value']);
        $this->assertSame(12.4, $card['pct']);
        $this->assertSame('good', $card['tone']);
        $this->assertSame('up', $card['direction']);
        $this->assertSame('vs same days last month', $card['note']);
    }

    public function test_daily_compares_with_yesterday_and_a_drop_is_red(): void
    {
        $this->pay('2026-11-27', '1650.00');
        $this->pay('2026-11-26', '1760.00');

        $card = $this->card('daily');

        $this->assertSame('Today · Fri 27 Nov', $card['label']);
        $this->assertSame(1650.0, $card['value']);
        $this->assertSame(-6.3, $card['pct']);
        $this->assertSame('bad', $card['tone']);
        $this->assertSame('down', $card['direction']);
    }

    public function test_yearly_compares_with_the_same_period_last_year(): void
    {
        $this->pay('2026-03-01', '419500.00');
        $this->pay('2025-06-01', '373400.00');
        $this->pay('2025-12-15', '50000.00');   // after 27 Nov last year — not compared

        $card = $this->card('yearly');

        $this->assertSame('This year · 1 Jan – 27 Nov', $card['label']);
        $this->assertSame(419500.0, $card['value']);
        $this->assertSame(12.3, $card['pct']);
        $this->assertSame('good', $card['tone']);
    }

    public function test_total_is_neutral_and_shows_what_this_month_added(): void
    {
        $this->pay('2024-05-01', '1554150.00');
        $this->pay('2026-11-10', '48350.00');

        $card = $this->card('total');

        $this->assertSame('All time', $card['label']);
        $this->assertSame(1602500.0, $card['value']);
        $this->assertSame(3.1, $card['pct']);
        $this->assertSame('neutral', $card['tone']);
        $this->assertSame('added this month', $card['note']);
    }

    public function test_revenue_with_nothing_earlier_to_compare_has_no_percentage(): void
    {
        $this->pay('2026-11-27', '500.00');

        $card = $this->card('daily');

        $this->assertNull($card['pct']);
        $this->assertSame('neutral', $card['tone']);
        $this->assertSame('no earlier revenue to compare', $card['note']);
    }

    public function test_the_card_renders_with_the_filter_and_the_expenses_total(): void
    {
        $this->pay('2026-11-27', '1650.00');
        $this->pay('2026-11-26', '1760.00');
        $category = ExpenseCategory::create(['name' => 'Rent']);
        Expense::create(['expense_category_id' => $category->id, 'amount' => '389750.00', 'expense_date' => '2026-11-01', 'created_by' => $this->admin->id]);

        Livewire::actingAs($this->admin)
            ->test(AdminDashboard::class)
            ->assertSee('Revenue')
            ->assertSee(['Daily', 'Monthly', 'Yearly', 'Total'])
            ->assertSee('Total expenses · all time')
            ->assertSee('389,750.00')
            ->call('setRevenuePeriod', 'daily')
            ->assertSet('revenuePeriod', 'daily')
            ->assertSee('−6.3%')
            ->assertSee('vs yesterday');
    }

    // ------------------------------------------------ the chart follows along

    private function dashboard()
    {
        return Livewire::actingAs($this->admin)->test(AdminDashboard::class);
    }

    public function test_daily_switches_the_chart_to_one_point_per_day(): void
    {
        $this->pay('2026-11-27', '1650.00');
        $this->pay('2026-11-26', '1760.00');
        $this->pay('2026-11-13', '999.00');   // 15 days ago — outside the 14-day default

        $component = $this->dashboard()->call('setRevenuePeriod', 'daily')
            ->assertSet('revenueRange', '14')
            ->assertSee('Daily Revenue')
            ->assertSee('Last 30 days');

        $series = $component->get('revenueSeries');
        $this->assertCount(14, $series['values']);
        $this->assertSame('14 Nov', $series['labels'][0]);
        $this->assertSame('27 Nov', end($series['labels']));
        $this->assertSame([1760.0, 1650.0], array_slice($series['values'], -2));
        $this->assertSame(3410.0, array_sum($series['values']));

        $this->assertCount(30, $component->set('revenueRange', '30')->get('revenueSeries.values'));
    }

    public function test_yearly_switches_the_chart_to_one_point_per_year(): void
    {
        $this->pay('2026-03-01', '419500.00');
        $this->pay('2025-06-01', '428600.00');
        $this->pay('2020-06-01', '5.00');      // older than 5 years — outside the default

        $series = $this->dashboard()->call('setRevenuePeriod', 'yearly')
            ->assertSet('revenueRange', '5')
            ->assertSee('Yearly Revenue')
            ->get('revenueSeries');

        $this->assertSame(['2022', '2023', '2024', '2025', '2026'], $series['labels']);
        $this->assertSame([0.0, 0.0, 0.0, 428600.0, 419500.0], $series['values']);
    }

    public function test_total_switches_the_chart_to_a_running_total(): void
    {
        $this->pay('2024-05-01', '1000.00');   // before the window: the starting point
        $this->pay('2026-10-05', '200.00');
        $this->pay('2026-11-10', '300.00');

        $series = $this->dashboard()->call('setRevenuePeriod', 'total')
            // Monthly's "6" also exists for Total, so it's kept…
            ->assertSet('revenueRange', '6')
            ->assertSee('Cumulative Revenue')
            // …then widened to 12 from the chart's own range menu.
            ->set('revenueRange', '12')
            ->get('revenueSeries');

        $this->assertCount(12, $series['values']);
        $this->assertSame('Dec 25', $series['labels'][0]);
        $this->assertSame('Nov 26', end($series['labels']));
        $this->assertSame(1000.0, $series['values'][0]);
        $this->assertSame([1200.0, 1500.0], array_slice($series['values'], -2));
    }

    public function test_a_range_that_also_exists_for_the_new_period_is_kept_otherwise_reset(): void
    {
        $this->dashboard()
            ->set('revenueRange', '12')               // 12 months
            ->call('setRevenuePeriod', 'total')
            ->assertSet('revenueRange', '12')         // 12 months cumulative — kept
            ->call('setRevenuePeriod', 'yearly')
            ->assertSet('revenueRange', '5')          // no 12 years — reset to default
            ->set('revenueRange', '999')              // not offered at all
            ->assertSet('revenueRange', '5');
    }

    public function test_a_live_payment_update_refreshes_the_chart(): void
    {
        $component = $this->dashboard();
        $this->assertSame(0.0, array_sum($component->get('revenueSeries.values')));

        $this->pay('2026-11-27', '750.00');
        $component->call('onRevenueUpdated', ['total_revenue' => '750.00']);

        $this->assertSame(750.0, array_sum($component->get('revenueSeries.values')));
    }

    public function test_an_unknown_period_is_ignored(): void
    {
        Livewire::actingAs($this->admin)
            ->test(AdminDashboard::class)
            ->call('setRevenuePeriod', 'hourly')
            ->assertSet('revenuePeriod', 'monthly');
    }
}
