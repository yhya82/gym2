<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Livewire\Volt\Volt;
use Tests\TestCase;

class SecurityLoggingTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_wrong_password_against_a_real_account_is_audit_logged(): void
    {
        $user = User::factory()->create(['role' => UserRole::Admin, 'email' => 'admin@example.com']);

        Volt::test('pages.auth.login')
            ->set('form.email', $user->email)
            ->set('form.password', 'wrong-password')
            ->call('login');

        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $user->id,
            'action' => 'failed-login',
            'module' => 'auth',
        ]);
    }

    /**
     * audit_logs.user_id is NOT NULL — an attempt against an email that
     * matches no account has nothing valid to attach a row to, so it must
     * not try to insert one (that would itself throw). It still isn't
     * silently dropped: it goes to the application log instead.
     */
    public function test_a_login_attempt_for_an_unknown_email_does_not_error_and_is_not_audit_logged(): void
    {
        Log::spy();

        Volt::test('pages.auth.login')
            ->set('form.email', 'nobody@example.com')
            ->set('form.password', 'whatever')
            ->call('login')
            ->assertHasErrors();

        $this->assertSame(0, AuditLog::count());
        Log::shouldHaveReceived('warning')->once();
    }

    public function test_a_permission_denial_is_audit_logged(): void
    {
        $receptionist = User::factory()->create(['role' => UserRole::Receptionist]);

        $this->actingAs($receptionist)->get('/plans')->assertForbidden();

        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $receptionist->id,
            'action' => 'permission-denied',
            'module' => 'auth',
        ]);
    }

    public function test_a_policy_level_denial_is_audit_logged_with_its_specific_message(): void
    {
        $onlyAdmin = User::factory()->create(['role' => UserRole::Admin]);

        $this->actingAs($onlyAdmin)->delete("/users/{$onlyAdmin->id}")->assertForbidden();

        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $onlyAdmin->id,
            'action' => 'permission-denied',
        ]);
        $log = AuditLog::where('action', 'permission-denied')->first();
        $this->assertStringContainsString('cannot deactivate your own account', $log->description);
    }
}
