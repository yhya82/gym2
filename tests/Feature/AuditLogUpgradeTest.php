<?php

namespace Tests\Feature;

use App\Enums\MembershipStatus;
use App\Enums\UserRole;
use App\Models\ApplicationSetting;
use App\Models\AuditLog;
use App\Models\Member;
use App\Models\Plan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * The structured audit entries (subject, Before/After changes, System
 * entries, device) and the viewer around them (sign-ins hidden by default,
 * quick ranges, local times, summary strip).
 */
class AuditLogUpgradeTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role' => UserRole::Admin, 'name' => 'Head Admin']);
    }

    private function entry(User $user, array $attributes = []): AuditLog
    {
        return AuditLog::create([
            'user_id' => $user->id,
            'action' => 'create',
            'module' => 'members',
            'description' => 'An entry',
            ...$attributes,
        ]);
    }

    // ------------------------------------------------------------- writing

    public function test_an_update_records_its_subject_and_before_after_values(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin);
        $member = Member::factory()->create(['full_name' => 'Jane Doe']);

        $member->update(['full_name' => 'Jane Smith']);

        $log = AuditLog::where('module', 'members')->where('action', 'update')->sole();
        $this->assertSame($member->getMorphClass(), $log->subject_type);
        $this->assertSame($member->id, $log->subject_id);
        $this->assertSame(['full_name' => ['old' => 'Jane Doe', 'new' => 'Jane Smith']], $log->changes);
        // The one-line description is still written, for search and CSV.
        $this->assertStringContainsString('full_name: Jane Doe → Jane Smith', $log->description);
    }

    public function test_the_browser_is_recorded_for_web_actions(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)
            ->withHeader('User-Agent', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 Chrome/131.0 Safari/537.36')
            ->post('/plans', ['plan_name' => 'Gold', 'duration_days' => 30, 'price' => '100.00']);

        $log = AuditLog::where('module', 'plans')->sole();
        $this->assertStringContainsString('Chrome/131.0', $log->user_agent);

        $this->get('/audit-logs?module=plans')->assertSee('Chrome · Windows', false);
    }

    public function test_the_expiry_job_writes_a_system_entry(): void
    {
        $plan = Plan::factory()->create(['plan_name' => 'Monthly', 'price' => '50.00']);
        $member = Member::factory()->create(['full_name' => 'Lapsed Lamin']);
        $member->subscriptions()->create([
            'plan_id' => $plan->id,
            'start_date' => now()->subDays(40),
            'expiry_date' => now()->subDay(),
            'plan_price' => $plan->price,
            'amount_paid' => $plan->price,
            'balance' => 0,
            'status' => MembershipStatus::Active,
        ]);

        $this->artisan('memberships:expire')->assertSuccessful();

        $log = AuditLog::where('action', 'expire')->sole();
        $this->assertNull($log->user_id);
        $this->assertTrue($log->isSystem());
        $this->assertSame($member->id, $log->subject_id);
        $this->assertSame(['status' => ['old' => 'active', 'new' => 'expired']], $log->changes);
        $this->assertStringContainsString('"Lapsed Lamin" (Monthly) expired', $log->description);

        $this->actingAs($this->admin())->get('/audit-logs?user=system')
            ->assertOk()
            ->assertSee('Lapsed Lamin', false)
            ->assertSee('System', false)
            ->assertSee('Expired', false);
    }

    // ------------------------------------------------------------- viewing

    public function test_sign_ins_are_hidden_unless_asked_for(): void
    {
        $admin = $this->admin();
        $this->entry($admin, ['module' => 'auth', 'action' => 'login', 'description' => 'Head Admin logged in.']);
        $this->entry($admin, ['description' => 'Registered member "Binta".']);

        $this->actingAs($admin);

        $this->get('/audit-logs')
            ->assertSee('Registered member', false)
            ->assertDontSee('Head Admin logged in.', false)
            ->assertSee('sign-ins hidden', false);

        $this->get('/audit-logs?signins=1')->assertSee('Head Admin logged in.', false);
        $this->get('/audit-logs?module=auth')->assertSee('Head Admin logged in.', false);
    }

    public function test_the_before_after_table_and_record_link_are_shown(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin);
        $member = Member::factory()->create(['full_name' => 'Jane Doe', 'phone_number' => '+220835190100']);
        $member->update(['full_name' => 'Jane Smith']);

        $this->get('/audit-logs?module=members&action=update')
            ->assertSee('Show change', false)
            ->assertSee('Before', false)
            ->assertSee('Name', false)
            ->assertSee(route('members.show', $member), false);
    }

    public function test_times_are_shown_in_the_gyms_timezone(): void
    {
        ApplicationSetting::current()->update(['timezone' => 'Asia/Tokyo']);
        $admin = $this->admin();

        // Stored in UTC; entries can't be updated, so the time is set on insert.
        AuditLog::query()->insert([
            'user_id' => $admin->id, 'action' => 'create', 'module' => 'members',
            'description' => 'Timed entry', 'created_at' => '2026-11-27 23:30:00',
        ]);

        $this->actingAs($admin)->get('/audit-logs?search=Timed')
            ->assertSee('Nov 28, 2026 08:30', false)
            ->assertSee('Times in Asia/Tokyo', false);
    }

    public function test_date_filters_are_read_as_the_gyms_calendar_days(): void
    {
        ApplicationSetting::current()->update(['timezone' => 'Asia/Tokyo']);
        $admin = $this->admin();

        // 23:30 UTC on the 27th is already the 28th in Tokyo.
        AuditLog::query()->insert([
            'user_id' => $admin->id, 'action' => 'create', 'module' => 'members',
            'description' => 'Tokyo morning entry', 'created_at' => '2026-11-27 23:30:00',
        ]);

        $this->actingAs($admin);
        $this->get('/audit-logs?from=2026-11-28&to=2026-11-28')->assertSee('Tokyo morning entry', false);
        $this->get('/audit-logs?from=2026-11-27&to=2026-11-27')->assertDontSee('Tokyo morning entry', false);
    }

    public function test_quick_range_today_hides_older_entries(): void
    {
        $admin = $this->admin();
        $this->entry($admin, ['description' => 'Fresh entry']);
        AuditLog::query()->insert([
            'user_id' => $admin->id, 'action' => 'create', 'module' => 'members',
            'description' => 'Last week entry', 'created_at' => Carbon::now()->subDays(8),
        ]);

        $this->actingAs($admin)->get('/audit-logs?range=today')
            ->assertSee('Fresh entry', false)
            ->assertDontSee('Last week entry', false);
    }

    public function test_the_summary_counts_recent_activity(): void
    {
        $admin = $this->admin();
        $this->entry($admin);
        $this->entry($admin, ['action' => 'update']);
        $this->entry($admin, ['module' => 'auth', 'action' => 'failed-login', 'description' => 'Failed login attempt']);

        $response = $this->actingAs($admin)->get('/audit-logs');

        $summary = $response->viewData('summary');
        $this->assertSame(3, $summary['today']);
        $this->assertSame(2, $summary['changes']);
        $this->assertSame(1, $summary['failed']);
        $this->assertSame(['name' => 'Head Admin', 'count' => 2], $summary['top']);
    }

    public function test_an_unknown_range_or_user_value_is_rejected(): void
    {
        $this->actingAs($this->admin());

        $this->get('/audit-logs?range=forever')->assertSessionHasErrors('range');
        $this->get('/audit-logs?user=drop-table')->assertSessionHasErrors('user');
    }
}
