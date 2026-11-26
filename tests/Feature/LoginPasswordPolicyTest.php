<?php

namespace Tests\Feature;

use App\Models\ApplicationSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Volt\Volt;
use Tests\TestCase;

class LoginPasswordPolicyTest extends TestCase
{
    use RefreshDatabase;

    private function tightenPolicy(): void
    {
        ApplicationSetting::current()->update([
            'password_min_length' => 12,
            'password_require_symbol' => true,
        ]);
    }

    private function login(string $email, string $password)
    {
        return Volt::test('pages.auth.login')
            ->set('form.email', $email)
            ->set('form.password', $password)
            ->call('login');
    }

    public function test_a_compliant_password_logs_in_normally(): void
    {
        User::factory()->create(['email' => 'staff@example.com']);

        $this->login('staff@example.com', 'password')
            ->assertHasNoErrors()
            ->assertRedirect(route('dashboard', absolute: false));

        $this->assertFalse(auth()->user()->must_change_password);
    }

    public function test_a_password_that_fails_the_current_policy_is_sent_to_the_change_screen(): void
    {
        $user = User::factory()->create(['email' => 'staff@example.com']);
        $this->tightenPolicy();

        $this->login('staff@example.com', 'password')
            ->assertHasNoErrors()
            ->assertRedirect(route('password.change-required'));

        $this->assertAuthenticatedAs($user);
        $this->assertTrue($user->fresh()->must_change_password);
        $this->assertDatabaseHas('audit_logs', ['action' => 'password-change-required', 'module' => 'security']);
    }

    public function test_a_flagged_user_cannot_reach_any_page_until_they_change_it(): void
    {
        $user = User::factory()->create();
        $user->forceFill(['must_change_password' => true])->save();

        foreach (['/dashboard', '/members', '/profile'] as $page) {
            $this->actingAs($user)->get($page)->assertRedirect(route('password.change-required'));
        }

        $this->actingAs($user)->get('/password/change')->assertOk()->assertSee('Set a new password');

        // Logging out stays possible.
        $this->actingAs($user)->post('/logout')->assertRedirect('/');
    }

    public function test_the_change_screen_lists_the_current_requirements_and_enforces_them(): void
    {
        $user = User::factory()->create();
        $user->forceFill(['must_change_password' => true])->save();
        $this->tightenPolicy();
        $this->actingAs($user);

        Volt::test('pages.auth.change-password')
            ->assertSee('At least 12 characters')
            ->assertSee('One symbol')
            ->set('current_password', 'password')
            // Long enough, but no symbol (a hyphen would count as one).
            ->set('password', 'stillnosymbol12')
            ->set('password_confirmation', 'stillnosymbol12')
            ->call('updatePassword')
            ->assertHasErrors('password');

        $this->assertTrue($user->fresh()->must_change_password);
    }

    public function test_setting_a_compliant_password_clears_the_requirement(): void
    {
        $user = User::factory()->create();
        $user->forceFill(['must_change_password' => true])->save();
        $this->tightenPolicy();
        $this->actingAs($user);

        Volt::test('pages.auth.change-password')
            ->set('current_password', 'password')
            ->set('password', 'Front-desk-2026!')
            ->set('password_confirmation', 'Front-desk-2026!')
            ->call('updatePassword')
            ->assertHasNoErrors()
            ->assertRedirect(route('dashboard', absolute: false));

        $user->refresh();
        $this->assertFalse($user->must_change_password);
        $this->assertTrue(Hash::check('Front-desk-2026!', $user->password));

        $this->get('/dashboard')->assertOk();
    }

    public function test_relaxing_the_policy_clears_the_requirement_at_the_next_login(): void
    {
        $user = User::factory()->create(['email' => 'staff@example.com']);
        $user->forceFill(['must_change_password' => true])->save();

        $this->login('staff@example.com', 'password')
            ->assertRedirect(route('dashboard', absolute: false));

        $this->assertFalse($user->fresh()->must_change_password);
    }

    public function test_an_admin_setting_a_new_password_also_clears_the_requirement(): void
    {
        $user = User::factory()->create();
        $user->forceFill(['must_change_password' => true])->save();

        $user->update(['password' => Hash::make('a-brand-new-one')]);

        $this->assertFalse($user->fresh()->must_change_password);
    }

    public function test_an_unflagged_user_visiting_the_change_screen_is_sent_to_the_dashboard(): void
    {
        $this->actingAs(User::factory()->create());

        Volt::test('pages.auth.change-password')
            ->assertRedirect(route('dashboard', absolute: false));
    }
}
