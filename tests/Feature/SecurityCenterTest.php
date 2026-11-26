<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Livewire\SecurityCenter;
use App\Models\ApplicationSetting;
use App\Models\LoginAttempt;
use App\Models\User;
use App\Services\SecurityScore;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

class SecurityCenterTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role' => UserRole::Admin]);
    }

    private function insertSession(string $id, User $user, int $secondsAgo = 0): void
    {
        DB::table('sessions')->insert([
            'id' => $id,
            'user_id' => $user->id,
            'ip_address' => '192.168.1.22',
            'user_agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/130.0 Safari/537.36',
            'payload' => '',
            'last_activity' => now()->subSeconds($secondsAgo)->getTimestamp(),
        ]);
    }

    // -------------------------------------------------------------- access

    public function test_admin_can_open_the_security_center(): void
    {
        $this->actingAs($this->admin())
            ->get('/settings/security')
            ->assertOk()
            ->assertSeeLivewire(SecurityCenter::class)
            ->assertSee('Security score');
    }

    public function test_receptionist_cannot_open_the_security_center(): void
    {
        $receptionist = User::factory()->create(['role' => UserRole::Receptionist]);

        $this->actingAs($receptionist)->get('/settings/security')->assertForbidden();

        Livewire::actingAs($receptionist)->test(SecurityCenter::class)->assertForbidden();
    }

    // -------------------------------------------------------------- policy

    public function test_the_policy_is_read_only_until_the_edit_button_is_pressed(): void
    {
        Livewire::actingAs($this->admin())
            ->test(SecurityCenter::class, ['tab' => 'policy'])
            ->assertSet('editing', false)
            ->assertDontSee('Save policy')
            ->call('startEditing')
            ->assertSet('editing', true)
            ->assertSee('Save policy');
    }

    public function test_saving_the_policy_persists_it_and_audit_logs_it_under_security(): void
    {
        Livewire::actingAs($this->admin())
            ->test(SecurityCenter::class, ['tab' => 'policy'])
            ->call('startEditing')
            ->set('password_min_length', 12)
            ->set('password_require_symbol', true)
            ->set('session_timeout_minutes', 30)
            ->call('savePolicy')
            ->assertHasNoErrors()
            ->assertSet('editing', false)
            ->assertDispatched('security-policy-saved');

        $policy = ApplicationSetting::securityPolicy();
        $this->assertSame(12, $policy['password_min_length']);
        $this->assertTrue($policy['password_require_symbol']);
        $this->assertSame(30, $policy['session_timeout_minutes']);

        $this->assertDatabaseHas('audit_logs', ['action' => 'update-policy', 'module' => 'security']);
        $this->assertDatabaseMissing('audit_logs', ['module' => 'settings']);
    }

    public function test_out_of_range_policy_values_are_rejected(): void
    {
        Livewire::actingAs($this->admin())
            ->test(SecurityCenter::class, ['tab' => 'policy'])
            ->call('startEditing')
            ->set('password_min_length', 4)
            ->set('lockout_attempts', 0)
            ->set('password_history', 30)
            ->set('session_timeout_minutes', 1)
            ->call('savePolicy')
            ->assertHasErrors(['password_min_length', 'lockout_attempts', 'password_history', 'session_timeout_minutes'])
            ->assertSet('editing', true);

        $this->assertSame(8, ApplicationSetting::securityPolicy()['password_min_length']);
    }

    public function test_cancel_discards_unsaved_changes(): void
    {
        Livewire::actingAs($this->admin())
            ->test(SecurityCenter::class, ['tab' => 'policy'])
            ->call('startEditing')
            ->set('password_min_length', 20)
            ->call('cancelEditing')
            ->assertSet('password_min_length', 8)
            ->assertSet('editing', false);
    }

    public function test_a_recommendations_fix_button_opens_the_policy_in_edit_mode(): void
    {
        Livewire::actingAs($this->admin())
            ->test(SecurityCenter::class)
            ->assertSee('Improve your password policy')
            ->call('fixPolicy')
            ->assertSet('tab', 'policy')
            ->assertSet('editing', true);
    }

    // ------------------------------------------------------- locked accounts

    public function test_admin_can_unlock_a_locked_account(): void
    {
        $locked = User::factory()->create();
        $locked->forceFill(['failed_login_attempts' => 5, 'locked_until' => now()->addMinutes(10)])->save();

        Livewire::actingAs($this->admin())
            ->test(SecurityCenter::class, ['tab' => 'locked'])
            ->assertSee($locked->name)
            ->call('unlock', $locked->id);

        $locked->refresh();
        $this->assertNull($locked->locked_until);
        $this->assertSame(0, $locked->failed_login_attempts);
        $this->assertDatabaseHas('audit_logs', ['action' => 'account-unlocked', 'module' => 'security']);
    }

    // -------------------------------------------------------------- sessions

    public function test_admin_can_revoke_another_users_session_by_its_hashed_key(): void
    {
        config(['session.driver' => 'database']);
        $staff = User::factory()->create();
        $this->insertSession('session-to-revoke', $staff);

        Livewire::actingAs($this->admin())
            ->test(SecurityCenter::class, ['tab' => 'sessions'])
            ->assertSee($staff->name)
            ->assertSee('Chrome · Windows')
            // The raw id is a login token and must never reach the page.
            ->assertDontSee('session-to-revoke')
            ->call('revokeSession', hash('sha256', 'session-to-revoke'));

        $this->assertDatabaseMissing('sessions', ['id' => 'session-to-revoke']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'session-revoked', 'module' => 'security']);
    }

    public function test_sign_out_everywhere_ends_all_sessions_and_rotates_the_remember_token(): void
    {
        config(['session.driver' => 'database']);
        $staff = User::factory()->create();
        $staff->forceFill(['remember_token' => 'old-token'])->save();
        $this->insertSession('s1', $staff);
        $this->insertSession('s2', $staff, 600);

        Livewire::actingAs($this->admin())
            ->test(SecurityCenter::class, ['tab' => 'sessions'])
            ->call('signOutEverywhere', $staff->id);

        $this->assertSame(0, DB::table('sessions')->where('user_id', $staff->id)->count());
        $this->assertNotSame('old-token', $staff->fresh()->remember_token);
    }

    public function test_sessions_past_the_timeout_are_not_listed_or_counted(): void
    {
        config(['session.driver' => 'database']);
        ApplicationSetting::current()->update(['session_timeout_minutes' => 30]);
        $staff = User::factory()->create(['name' => 'Stale Session User']);
        $this->insertSession('stale', $staff, 31 * 60);

        Livewire::actingAs($this->admin())
            ->test(SecurityCenter::class, ['tab' => 'sessions'])
            ->assertDontSee('Stale Session User')
            ->assertSee('No active sessions.');
    }

    // ----------------------------------------------------------------- stats

    public function test_stat_cards_count_login_attempts_locks_and_online_users(): void
    {
        config(['session.driver' => 'database']);
        $staff = User::factory()->create();
        $staff->forceFill(['locked_until' => now()->addMinutes(5)])->save();

        LoginAttempt::create(['email' => 'a@example.com', 'successful' => true]);
        LoginAttempt::create(['email' => 'a@example.com', 'successful' => true]);
        LoginAttempt::create(['email' => 'b@example.com', 'successful' => false]);

        $this->insertSession('online', $staff, 60);
        $this->insertSession('idle', $staff, 20 * 60);

        $stats = Livewire::actingAs($this->admin())->test(SecurityCenter::class)->instance()->stats;

        $this->assertSame([
            'total_logins' => 3,
            'successful_logins' => 2,
            'failed_logins' => 1,
            'locked_accounts' => 1,
            'active_sessions' => 2,
            'online_users' => 1,
        ], $stats);
    }

    // ----------------------------------------------------------------- score

    public function test_the_default_policy_scores_low_and_lists_every_improvement(): void
    {
        $result = app(SecurityScore::class)->evaluate(ApplicationSetting::SECURITY_DEFAULTS);

        // 8 chars (10) + no character rules (0) + 120-min timeout (6)
        // + 5-attempt lockout (12) + 10-min lockout (10) + no history (0).
        $this->assertSame(38, $result['score']);
        $this->assertSame('Weak', $result['grade']);
        $this->assertSame(62, array_sum(array_column($result['recommendations'], 'points')));
    }

    public function test_the_recommended_policy_scores_one_hundred(): void
    {
        $result = app(SecurityScore::class)->evaluate([
            'password_min_length' => 12,
            'password_require_uppercase' => true,
            'password_require_lowercase' => true,
            'password_require_number' => true,
            'password_require_symbol' => true,
            'lockout_attempts' => 5,
            'lockout_minutes' => 10,
            'password_history' => 5,
            'session_timeout_minutes' => 30,
        ]);

        $this->assertSame(100, $result['score']);
        $this->assertSame('Strong', $result['grade']);
        $this->assertSame([], $result['recommendations']);
    }
}
