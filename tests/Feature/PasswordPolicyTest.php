<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\ApplicationSetting;
use App\Models\LoginAttempt;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Volt\Volt;
use Tests\TestCase;

class PasswordPolicyTest extends TestCase
{
    use RefreshDatabase;

    private function setPolicy(array $values): void
    {
        ApplicationSetting::current()->update($values);
    }

    private function changePasswordAs(User $user)
    {
        $this->actingAs($user);

        return Volt::test('profile.update-password-form');
    }

    private function createUserPayload(string $password): array
    {
        return [
            'name' => 'New Staff',
            'email' => 'staff@example.com',
            'phone_number' => '+220835190199',
            'password' => $password,
            'role' => 'receptionist',
        ];
    }

    // ------------------------------------------------------- password rules

    public function test_the_default_policy_keeps_the_previous_eight_character_minimum(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);

        $this->actingAs($admin)->post('/users', $this->createUserPayload('short'))
            ->assertSessionHasErrors('password');

        $this->actingAs($admin)->post('/users', $this->createUserPayload('abcdefgh'))
            ->assertSessionHasNoErrors();
    }

    public function test_required_character_types_and_length_are_enforced_when_creating_a_user(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $this->setPolicy([
            'password_min_length' => 10,
            'password_require_uppercase' => true,
            'password_require_lowercase' => true,
            'password_require_number' => true,
            'password_require_symbol' => true,
        ]);

        foreach (['Short1!', 'alllowercase1!', 'ALLUPPERCASE1!', 'NoNumbersHere!', 'NoSymbols123'] as $weak) {
            $this->actingAs($admin)->post('/users', $this->createUserPayload($weak))
                ->assertSessionHasErrors('password');
        }

        $this->actingAs($admin)->post('/users', $this->createUserPayload('Front-Desk-24'))
            ->assertSessionHasNoErrors();
        $this->assertDatabaseHas('users', ['email' => 'staff@example.com']);
    }

    public function test_the_profile_password_form_uses_the_same_policy(): void
    {
        $user = User::factory()->create();
        $this->setPolicy(['password_require_symbol' => true]);

        $this->changePasswordAs($user)
            ->set('current_password', 'password')
            ->set('password', 'no-symbol-is-fine?')
            ->set('password_confirmation', 'no-symbol-is-fine?')
            ->call('updatePassword')
            ->assertHasNoErrors();

        $this->changePasswordAs($user->fresh())
            ->set('current_password', 'no-symbol-is-fine?')
            ->set('password', 'nosymbolatall')
            ->set('password_confirmation', 'nosymbolatall')
            ->call('updatePassword')
            ->assertHasErrors(['password']);
    }

    // ----------------------------------------------------- password history

    public function test_recent_passwords_cannot_be_reused_when_history_is_on(): void
    {
        $user = User::factory()->create();
        $this->setPolicy(['password_history' => 3]);

        $change = fn (string $from, string $to) => $this->changePasswordAs($user->fresh())
            ->set('current_password', $from)
            ->set('password', $to)
            ->set('password_confirmation', $to)
            ->call('updatePassword');

        // Same as the current password.
        $change('password', 'password')->assertHasErrors(['password']);

        $change('password', 'second-pass')->assertHasNoErrors();
        $change('second-pass', 'third-pass')->assertHasNoErrors();

        // "password" is still within the last 3.
        $change('third-pass', 'password')->assertHasErrors(['password']);

        $change('third-pass', 'fourth-pass')->assertHasNoErrors();
        $change('fourth-pass', 'fifth-pass')->assertHasNoErrors();

        // Now four changes back, so it's allowed again.
        $change('fifth-pass', 'password')->assertHasNoErrors();
        $this->assertTrue(Hash::check('password', $user->fresh()->password));
    }

    public function test_reuse_is_allowed_when_history_is_off(): void
    {
        $user = User::factory()->create();

        $this->changePasswordAs($user)
            ->set('current_password', 'password')
            ->set('password', 'password')
            ->set('password_confirmation', 'password')
            ->call('updatePassword')
            ->assertHasNoErrors();
    }

    public function test_an_admin_resetting_a_users_password_is_also_checked_against_history(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $staff = User::factory()->create(['role' => UserRole::Receptionist, 'phone_number' => '+220835190198']);
        $this->setPolicy(['password_history' => 2]);

        $this->actingAs($admin)->put("/users/{$staff->id}", [
            'name' => $staff->name,
            'email' => $staff->email,
            'phone_number' => '+220835190198',
            'password' => 'password',
            'role' => 'receptionist',
        ])->assertSessionHasErrors('password');
    }

    public function test_a_password_change_is_recorded_in_history(): void
    {
        $user = User::factory()->create();
        $this->assertSame(1, $user->passwordHistories()->count());

        $user->update(['password' => Hash::make('another-one')]);
        $this->assertSame(2, $user->passwordHistories()->count());

        // Saving without touching the password adds nothing.
        $user->update(['name' => 'Renamed']);
        $this->assertSame(2, $user->passwordHistories()->count());
    }

    // -------------------------------------------------------------- lockout

    private function attemptLogin(string $email, string $password)
    {
        return Volt::test('pages.auth.login')
            ->set('form.email', $email)
            ->set('form.password', $password)
            ->call('login');
    }

    public function test_the_account_locks_after_the_configured_attempts_for_the_configured_minutes(): void
    {
        $user = User::factory()->create(['email' => 'staff@example.com']);
        $this->setPolicy(['lockout_attempts' => 3, 'lockout_minutes' => 15]);

        $this->attemptLogin('staff@example.com', 'wrong');
        $this->attemptLogin('staff@example.com', 'wrong');
        $this->assertNull($user->fresh()->locked_until);

        $this->attemptLogin('staff@example.com', 'wrong');

        $lockedUntil = $user->fresh()->locked_until;
        $this->assertNotNull($lockedUntil);
        $this->assertEqualsWithDelta(15 * 60, now()->diffInSeconds($lockedUntil), 5);
        $this->assertDatabaseHas('audit_logs', ['user_id' => $user->id, 'action' => 'account-locked', 'module' => 'security']);

        // Even the right password is refused while locked.
        $this->attemptLogin('staff@example.com', 'password')->assertHasErrors('form.email');
        $this->assertGuest();
    }

    public function test_the_failed_attempt_counter_starts_over_once_a_lock_expires(): void
    {
        $user = User::factory()->create(['email' => 'staff@example.com']);
        $user->forceFill(['failed_login_attempts' => 5, 'locked_until' => now()->subMinute()])->save();

        $this->attemptLogin('staff@example.com', 'wrong');

        $user->refresh();
        $this->assertSame(1, $user->failed_login_attempts);
        $this->assertNull($user->locked_until);
    }

    public function test_unknown_emails_are_throttled_by_the_same_attempt_limit(): void
    {
        $this->setPolicy(['lockout_attempts' => 2]);

        $this->attemptLogin('nobody@example.com', 'x');
        $this->attemptLogin('nobody@example.com', 'x');

        $this->attemptLogin('nobody@example.com', 'x')
            ->assertHasErrors('form.email');

        // The third attempt never reached Auth::attempt, so only two were recorded.
        $this->assertSame(2, LoginAttempt::where('email', 'nobody@example.com')->count());
    }

    public function test_every_login_attempt_is_recorded(): void
    {
        $user = User::factory()->create(['email' => 'staff@example.com']);

        $this->attemptLogin('staff@example.com', 'wrong');
        $this->attemptLogin('nobody@example.com', 'wrong');
        $this->attemptLogin('staff@example.com', 'password');

        $this->assertDatabaseHas('login_attempts', ['user_id' => $user->id, 'successful' => false]);
        $this->assertDatabaseHas('login_attempts', ['user_id' => null, 'email' => 'nobody@example.com', 'successful' => false]);
        $this->assertDatabaseHas('login_attempts', ['user_id' => $user->id, 'successful' => true]);
    }

    // ------------------------------------------------------ session timeout

    public function test_the_session_timeout_setting_replaces_the_configured_lifetime(): void
    {
        $this->setPolicy(['session_timeout_minutes' => 30]);

        $this->get('/login')->assertOk();

        $this->assertSame(30, config('session.lifetime'));
    }
}
