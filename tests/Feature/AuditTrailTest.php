<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Livewire\ChangePasswordModal;
use App\Livewire\ProfileDetails;
use App\Models\AuditLog;
use App\Models\LoginAttempt;
use App\Models\Member;
use App\Models\Plan;
use App\Models\User;
use App\Services\MemberRegistrationService;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Livewire\Volt\Volt;
use Tests\TestCase;

class AuditTrailTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role' => UserRole::Admin, 'name' => 'Head Admin']);
    }

    private function latestLog(): AuditLog
    {
        return AuditLog::latest('id')->firstOrFail();
    }

    // ----------------------------------------------------- user management

    public function test_creating_a_user_is_logged(): void
    {
        $this->actingAs($this->admin())->post('/users', [
            'name' => 'New Receptionist',
            'email' => 'new@example.com',
            'phone_number' => '+220835190199',
            'password' => 'password',
            'password_confirmation' => 'password',
            'role' => 'receptionist',
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'create',
            'module' => 'users',
            'description' => 'Created user "New Receptionist" (new@example.com) with role receptionist.',
        ]);
    }

    public function test_a_role_change_is_logged_with_old_and_new_values(): void
    {
        $admin = $this->admin();
        $staff = User::factory()->create(['role' => UserRole::Receptionist, 'name' => 'Awa', 'phone_number' => '+220835190198']);

        $this->actingAs($admin)->put("/users/{$staff->id}", [
            'name' => 'Awa',
            'email' => $staff->email,
            'phone_number' => '+220835190198',
            'role' => 'admin',
        ])->assertSessionHasNoErrors();

        $log = AuditLog::where('module', 'users')->where('action', 'update')->sole();
        $this->assertSame($admin->id, $log->user_id);
        $this->assertStringContainsString('role: receptionist → admin', $log->description);
    }

    public function test_an_admin_setting_someones_password_is_logged_without_the_hash(): void
    {
        $staff = User::factory()->create(['name' => 'Awa', 'phone_number' => '+220835190198']);

        Livewire::actingAs($this->admin())
            ->test(ChangePasswordModal::class)
            ->call('open', $staff->id)
            ->set('password', 'a-new-password')
            ->set('password_confirmation', 'a-new-password')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('audit_logs', ['action' => 'password-set', 'module' => 'security', 'description' => 'Set a new password for "Awa".']);

        foreach (AuditLog::pluck('description') as $description) {
            $this->assertStringNotContainsString('$2y$', $description, 'A password hash leaked into the audit log.');
        }
    }

    public function test_deactivating_a_user_is_logged(): void
    {
        $this->admin(); // keeps a second Admin around
        $admin = $this->admin();
        $staff = User::factory()->create(['name' => 'Leaving Soon']);

        $this->actingAs($admin)->delete("/users/{$staff->id}")->assertRedirect();

        $this->assertDatabaseHas('audit_logs', ['action' => 'deactivate', 'module' => 'users', 'user_id' => $admin->id]);
    }

    public function test_editing_your_own_profile_and_password_is_logged(): void
    {
        $user = User::factory()->create(['name' => 'Fatou', 'phone_number' => '+220835190199']);
        $this->actingAs($user);

        Livewire::test(ProfileDetails::class)
            ->call('startEditing')
            ->set('name', 'Fatou Jallow')
            ->call('save');

        Livewire::test(ChangePasswordModal::class)
            ->call('open')
            ->set('password', 'another-password')
            ->set('password_confirmation', 'another-password')
            ->call('save');

        $this->assertDatabaseHas('audit_logs', ['action' => 'update', 'module' => 'users', 'description' => 'Fatou Jallow updated their own profile (name: Fatou → Fatou Jallow).']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'password-changed', 'user_id' => $user->id, 'description' => 'Fatou Jallow changed their password.']);
    }

    public function test_a_password_reset_by_emailed_link_is_attributed_to_the_account_owner(): void
    {
        $user = User::factory()->create(['name' => 'Lamin']);
        $this->assertFalse(Auth::check());

        $user->update(['password' => Hash::make('reset-password')]);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'password-changed',
            'user_id' => $user->id,
            'description' => 'Lamin reset their password using an emailed link.',
        ]);
    }

    public function test_requesting_a_reset_link_is_logged_for_real_accounts_only(): void
    {
        Notification::fake();
        $user = User::factory()->create(['email' => 'staff@example.com']);

        Volt::test('pages.auth.forgot-password')->set('email', 'staff@example.com')->call('sendPasswordResetLink');
        Volt::test('pages.auth.forgot-password')->set('email', 'nobody@example.com')->call('sendPasswordResetLink');

        $this->assertSame(1, AuditLog::where('action', 'password-reset-requested')->count());
        $this->assertDatabaseHas('audit_logs', ['action' => 'password-reset-requested', 'user_id' => $user->id]);
    }

    public function test_login_and_lockout_bookkeeping_does_not_create_user_update_entries(): void
    {
        $user = User::factory()->create(['email' => 'staff@example.com']);

        Volt::test('pages.auth.login')->set('form.email', 'staff@example.com')->set('form.password', 'wrong')->call('login');
        Volt::test('pages.auth.login')->set('form.email', 'staff@example.com')->set('form.password', 'password')->set('form.remember', true)->call('login');

        $this->assertSame(0, AuditLog::where('module', 'users')->where('action', 'update')->count());
    }

    // -------------------------------------------------- members & payments

    private function registerMember(User $staff, string $name = 'Jane Doe'): Member
    {
        $this->actingAs($staff);

        return app(MemberRegistrationService::class)->register(
            $name, '+220835190199', Plan::factory()->create(['price' => '100.00', 'duration_days' => 30]),
            now(), '100.00', $staff,
        );
    }

    public function test_payment_entries_name_the_member(): void
    {
        $this->registerMember($this->admin());

        $this->assertDatabaseHas('audit_logs', ['module' => 'payments', 'description' => 'Recorded payment of 100.00 for "Jane Doe".']);
    }

    public function test_restoring_an_archived_member_is_logged(): void
    {
        $admin = $this->admin();
        $member = $this->registerMember($admin);
        $member->delete();

        $this->actingAs($admin)->post("/members/{$member->id}/restore")->assertRedirect();

        $this->assertDatabaseHas('audit_logs', ['action' => 'restore', 'module' => 'members', 'description' => 'Restored archived member "Jane Doe".']);
    }

    public function test_exporting_members_is_logged(): void
    {
        $admin = $this->admin();
        $this->registerMember($admin);

        $this->actingAs($admin)->get('/members/export?status=active')->assertOk();

        $this->assertDatabaseHas('audit_logs', ['action' => 'export', 'module' => 'members', 'description' => 'Exported 1 member(s) to CSV (filter: active).']);
    }

    // ------------------------------------------------- append-only & retention

    private function insertEntry(Carbon $at, string $description = 'Old entry'): int
    {
        return DB::table('audit_logs')->insertGetId([
            'user_id' => User::factory()->create()->id,
            'action' => 'create',
            'module' => 'members',
            'description' => $description,
            'created_at' => $at,
        ]);
    }

    public function test_the_database_rejects_editing_an_entry(): void
    {
        $id = $this->insertEntry(now());

        $this->expectException(QueryException::class);
        $this->expectExceptionMessage('Audit log entries cannot be changed');

        DB::table('audit_logs')->where('id', $id)->update(['description' => 'Tampered']);
    }

    public function test_the_database_rejects_deleting_an_entry_younger_than_twelve_months(): void
    {
        $id = $this->insertEntry(now()->subMonths(11));

        $this->expectException(QueryException::class);
        $this->expectExceptionMessage('cannot be deleted until they are 12 months old');

        DB::table('audit_logs')->where('id', $id)->delete();
    }

    public function test_pruning_removes_only_entries_past_retention(): void
    {
        $old = $this->insertEntry(now()->subMonths(13), 'Expired entry');
        $recent = $this->insertEntry(now()->subMonths(6), 'Recent entry');
        LoginAttempt::insert([
            ['email' => 'old@example.com', 'successful' => true, 'created_at' => now()->subMonths(13)],
            ['email' => 'new@example.com', 'successful' => true, 'created_at' => now()->subMonth()],
        ]);

        $this->artisan('model:prune', ['--model' => [AuditLog::class, LoginAttempt::class]])->assertSuccessful();

        $this->assertDatabaseMissing('audit_logs', ['id' => $old]);
        $this->assertDatabaseHas('audit_logs', ['id' => $recent]);
        $this->assertSame(['new@example.com'], LoginAttempt::pluck('email')->all());
    }

    // ------------------------------------------------------------- the page

    public function test_actions_are_shown_with_readable_labels(): void
    {
        $admin = $this->admin();
        AuditLog::create(['user_id' => $admin->id, 'action' => 'failed-login', 'module' => 'auth', 'description' => 'x']);

        $this->actingAs($admin)->get('/audit-logs')
            ->assertSee('Failed login')
            ->assertDontSee('Failed-login');
    }

    public function test_entries_can_be_filtered_by_action_user_and_date(): void
    {
        $admin = $this->admin();
        $other = User::factory()->create(['name' => 'Someone Else']);

        AuditLog::create(['user_id' => $admin->id, 'action' => 'create', 'module' => 'plans', 'description' => 'Admin created']);
        AuditLog::create(['user_id' => $other->id, 'action' => 'update', 'module' => 'plans', 'description' => 'Other updated']);
        $this->insertEntry(now()->subDays(10), 'Ten days ago');

        $this->actingAs($admin)->get('/audit-logs?action=update')
            ->assertSee('Other updated')->assertDontSee('Admin created');

        $this->actingAs($admin)->get("/audit-logs?user={$admin->id}")
            ->assertSee('Admin created')->assertDontSee('Other updated');

        $this->actingAs($admin)->get('/audit-logs?to='.now()->subDays(5)->toDateString())
            ->assertSee('Ten days ago')->assertDontSee('Admin created');
    }

    public function test_csv_export_follows_the_filter_logs_itself_and_neutralises_formulas(): void
    {
        $admin = $this->admin();
        AuditLog::create(['user_id' => $admin->id, 'action' => 'create', 'module' => 'members', 'description' => '=HYPERLINK("http://evil")']);
        AuditLog::create(['user_id' => $admin->id, 'action' => 'create', 'module' => 'plans', 'description' => 'Plan entry']);

        $csv = $this->actingAs($admin)->get('/audit-logs/export?module=members')
            ->assertOk()
            ->streamedContent();

        $this->assertStringContainsString("'=HYPERLINK", $csv);
        $this->assertStringNotContainsString('Plan entry', $csv);
        // The export entry is written before streaming, but isn't in its own file.
        $this->assertStringNotContainsString('Exported', $csv);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'export',
            'module' => 'audit',
            'description' => 'Exported 1 audit log entry to CSV (filter: module members).',
        ]);
    }

    public function test_receptionists_cannot_export_the_audit_log(): void
    {
        $receptionist = User::factory()->create(['role' => UserRole::Receptionist]);

        $this->actingAs($receptionist)->get('/audit-logs/export')->assertForbidden();
    }

    public function test_a_blocked_livewire_request_from_a_flagged_user_is_not_logged_as_a_permission_denial(): void
    {
        $user = User::factory()->create();
        $user->forceFill(['must_change_password' => true])->save();

        $this->actingAs($user)
            ->withHeader('X-Livewire', 'true')
            ->get('/members')
            ->assertForbidden();

        $this->assertDatabaseMissing('audit_logs', ['action' => 'permission-denied']);
    }
}
