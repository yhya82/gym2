<?php

namespace Tests\Feature\Auth;

use App\Enums\UserRole;
use App\Livewire\ChangePasswordModal;
use App\Models\ApplicationSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * The Change password modal (App\Livewire\ChangePasswordModal): your own
 * password from Profile, or — Admins only — someone else's from Users.
 */
class PasswordUpdateTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_user_can_change_their_own_password_with_new_and_confirm_fields(): void
    {
        $user = User::factory()->create();

        Livewire::actingAs($user)
            ->test(ChangePasswordModal::class)
            ->call('open')
            ->set('password', 'new-password')
            ->set('password_confirmation', 'new-password')
            ->call('save')
            ->assertHasNoErrors()
            ->assertDispatched('close-modal', 'change-password');

        $user->refresh();
        $this->assertTrue(Hash::check('new-password', $user->password));
        // Your own change never forces another change at next login.
        $this->assertFalse($user->must_change_password);
    }

    public function test_mismatched_confirmation_gets_a_clear_message(): void
    {
        Livewire::actingAs(User::factory()->create())
            ->test(ChangePasswordModal::class)
            ->call('open')
            ->set('password', 'new-password')
            ->set('password_confirmation', 'different')
            ->call('save')
            ->assertHasErrors(['password' => "The passwords don't match."]);
    }

    public function test_each_broken_policy_rule_gets_its_own_message(): void
    {
        ApplicationSetting::current()->update([
            'password_min_length' => 12,
            'password_require_uppercase' => true,
            'password_require_symbol' => true,
        ]);

        $component = Livewire::actingAs(User::factory()->create())
            ->test(ChangePasswordModal::class)
            ->call('open')
            ->set('password', 'short')
            ->set('password_confirmation', 'short')
            ->call('save');

        $messages = $component->errors()->get('password');
        $this->assertContains('The new password field must be at least 12 characters.', $messages);
        $this->assertContains('The new password must contain at least one uppercase letter.', $messages);
        $this->assertContains('The new password must contain at least one symbol.', $messages);
    }

    public function test_the_modal_lists_the_current_policy(): void
    {
        ApplicationSetting::current()->update(['password_min_length' => 14, 'password_require_number' => true]);

        $this->actingAs(User::factory()->create())
            ->get('/profile')
            ->assertSee('At least 14 characters')
            ->assertSee('One number (0–9)');
    }

    public function test_an_admin_changing_someone_elses_password_forces_a_change_at_next_login(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $staff = User::factory()->create(['role' => UserRole::Receptionist]);

        Livewire::actingAs($admin)
            ->test(ChangePasswordModal::class)
            ->call('open', $staff->id)
            ->assertSee('Change password for '.$staff->name)
            ->set('password', 'temporary-pass')
            ->set('password_confirmation', 'temporary-pass')
            ->call('save')
            ->assertHasNoErrors();

        $staff->refresh();
        $this->assertTrue(Hash::check('temporary-pass', $staff->password));
        $this->assertTrue($staff->must_change_password);
    }

    public function test_a_receptionist_cannot_change_another_users_password(): void
    {
        $receptionist = User::factory()->create(['role' => UserRole::Receptionist]);
        $other = User::factory()->create();

        Livewire::actingAs($receptionist)
            ->test(ChangePasswordModal::class)
            ->call('open', $other->id)
            ->assertForbidden();

        $this->assertTrue(Hash::check('password', $other->fresh()->password));
    }

    public function test_the_target_user_cannot_be_swapped_after_opening(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();

        $this->expectException(CannotUpdateLockedPropertyException::class);

        Livewire::actingAs($user)
            ->test(ChangePasswordModal::class)
            ->call('open')
            ->set('userId', $other->id);
    }
}
