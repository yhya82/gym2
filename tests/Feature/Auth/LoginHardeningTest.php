<?php

namespace Tests\Feature\Auth;

use App\Livewire\Forms\LoginForm;
use App\Livewire\ProfileDetails;
use App\Models\LoginAttempt;
use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Livewire\Volt\Volt;
use Tests\TestCase;

/**
 * The four follow-ups from the login review: per-IP throttling across
 * accounts, a forgot-password page that doesn't reveal accounts, recording
 * refused attempts, and no false promise of email verification.
 */
class LoginHardeningTest extends TestCase
{
    use RefreshDatabase;

    private function login(string $email, string $password)
    {
        return Volt::test('pages.auth.login')
            ->set('form.email', $email)
            ->set('form.password', $password)
            ->call('login');
    }

    // ------------------------------------------------- 1. per-IP throttling

    public function test_one_ip_spraying_many_accounts_is_throttled(): void
    {
        Log::spy();
        User::factory()->create(['email' => 'real@example.com']);

        // Each email only ever sees one failure, so no per-account lock fires…
        for ($i = 0; $i < LoginForm::IP_MAX_FAILURES; $i++) {
            $this->login("guess{$i}@example.com", 'Summer2026!')->assertHasErrors('form.credentials');
        }

        // …but the IP as a whole is now cut off, for any email — even a real
        // account with the right password.
        $this->login('real@example.com', 'password')
            ->assertHasErrors(['form.locked' => trans('auth.ip_throttled')]);
        $this->assertGuest();

        Log::shouldHaveReceived('warning')->withArgs(fn ($m) => str_contains($m, 'Login throttled for IP'))->once();
    }

    public function test_normal_typos_stay_well_under_the_ip_ceiling(): void
    {
        User::factory()->create(['email' => 'staff@example.com']);

        $this->login('staff@example.com', 'typo')->assertHasErrors('form.credentials');
        $this->login('staff@example.com', 'password')->assertHasNoErrors();
    }

    // ---------------------------------------- 2. generic forgot-password reply

    public function test_forgot_password_replies_the_same_whether_or_not_the_account_exists(): void
    {
        Notification::fake();
        $user = User::factory()->create(['email' => 'staff@example.com']);
        $generic = 'If that email address has an account, we have sent a password reset link to it.';

        Volt::test('pages.auth.forgot-password')
            ->set('email', 'nobody@example.com')
            ->call('sendPasswordResetLink')
            ->assertHasNoErrors()
            ->assertSee($generic);

        Volt::test('pages.auth.forgot-password')
            ->set('email', 'staff@example.com')
            ->call('sendPasswordResetLink')
            ->assertHasNoErrors()
            ->assertSee($generic);
        Notification::assertSentTo($user, ResetPassword::class);

        // Laravel's per-account "please wait" throttle would also give the
        // account away — it gets the same reply too.
        Volt::test('pages.auth.forgot-password')
            ->set('email', 'staff@example.com')
            ->call('sendPasswordResetLink')
            ->assertHasNoErrors()
            ->assertSee($generic);
    }

    public function test_forgot_password_is_rate_limited_per_ip(): void
    {
        Notification::fake();

        for ($i = 0; $i < 5; $i++) {
            Volt::test('pages.auth.forgot-password')->set('email', "a{$i}@example.com")->call('sendPasswordResetLink')->assertHasNoErrors();
        }

        Volt::test('pages.auth.forgot-password')
            ->set('email', 'a9@example.com')
            ->call('sendPasswordResetLink')
            ->assertHasErrors(['email' => 'Too many reset requests from this device. Try again later.']);
    }

    // ------------------------------------------- 3. refused attempts recorded

    public function test_attempts_refused_while_an_account_is_locked_are_recorded(): void
    {
        $user = User::factory()->create(['email' => 'staff@example.com']);
        $user->forceFill(['locked_until' => now()->addMinutes(10)])->save();

        $this->login('staff@example.com', 'password')->assertHasErrors('form.locked');
        $this->login('staff@example.com', 'anything')->assertHasErrors('form.locked');

        $this->assertSame(2, LoginAttempt::where('user_id', $user->id)->where('successful', false)->count());
    }

    // ------------------------------------------- 4. no false verification note

    public function test_the_profile_no_longer_promises_email_verification(): void
    {
        $user = User::factory()->create(['phone_number' => '+220835190199']);

        Livewire::actingAs($user)
            ->test(ProfileDetails::class)
            ->call('startEditing')
            ->assertDontSee('verifying the new address');
    }
}
