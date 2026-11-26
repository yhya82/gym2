<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Livewire\ProfileDetails;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\TestCase;

class UserManagementTest extends TestCase
{
    use RefreshDatabase;

    private function admin(array $attributes = []): User
    {
        return User::factory()->create(['role' => UserRole::Admin, ...$attributes]);
    }

    private function updatePayload(User $user, array $overrides = []): array
    {
        // fresh(): a factory user's role is the DB column default, which
        // isn't on the in-memory model until it's reloaded.
        $user = $user->fresh();

        return [
            'name' => $user->name,
            'email' => $user->email,
            'phone_number' => $user->phone_number ?? '+220835190111',
            'role' => $user->role->value,
            ...$overrides,
        ];
    }

    // ------------------------------------------------------------ last Admin

    public function test_the_only_admin_cannot_demote_themselves(): void
    {
        $onlyAdmin = $this->admin(['phone_number' => '+220835190111']);

        $this->actingAs($onlyAdmin)
            ->put("/users/{$onlyAdmin->id}", $this->updatePayload($onlyAdmin, ['role' => 'receptionist']))
            ->assertSessionHasErrors('role');

        $this->assertSame(UserRole::Admin, $onlyAdmin->fresh()->role);
    }

    /**
     * Defence in depth behind the "own role" rule: the policy refuses to
     * demote whoever is the last active Admin, whoever asks.
     */
    public function test_the_policy_refuses_to_demote_the_last_active_admin(): void
    {
        $lastAdmin = $this->admin();
        // An Admin who has since been deactivated no longer counts.
        $formerAdmin = $this->admin();
        $formerAdmin->delete();

        $decision = Gate::forUser($formerAdmin)->inspect('changeRole', $lastAdmin);

        $this->assertTrue($decision->denied());
        $this->assertSame('This is the only Admin account. Make another user an Admin first.', $decision->message());
    }

    public function test_an_admin_cannot_change_their_own_role_even_when_other_admins_exist(): void
    {
        $admin = $this->admin(['phone_number' => '+220835190111']);
        $this->admin(['phone_number' => '+220835190112']);

        $this->actingAs($admin)
            ->put("/users/{$admin->id}", $this->updatePayload($admin, ['role' => 'receptionist']))
            ->assertSessionHasErrors(['role' => 'You cannot change your own role.']);

        $this->assertSame(UserRole::Admin, $admin->fresh()->role);
    }

    public function test_an_admin_can_demote_another_admin_when_one_would_remain(): void
    {
        $admin = $this->admin(['phone_number' => '+220835190111']);
        $other = $this->admin(['phone_number' => '+220835190112']);

        $this->actingAs($admin)
            ->put("/users/{$other->id}", $this->updatePayload($other, ['role' => 'receptionist']))
            ->assertSessionHasNoErrors();

        $this->assertSame(UserRole::Receptionist, $other->fresh()->role);
    }

    public function test_the_edit_form_locks_the_role_select_with_a_reason(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)
            ->get('/users')
            ->assertOk()
            ->assertSee('You cannot change your own role.');
    }

    public function test_saving_details_without_a_role_keeps_the_current_role(): void
    {
        $admin = $this->admin(['phone_number' => '+220835190111']);

        // A disabled select isn't submitted at all.
        $payload = $this->updatePayload($admin, ['name' => 'Renamed Admin']);
        unset($payload['role']);

        $this->actingAs($admin)->put("/users/{$admin->id}", $payload)->assertSessionHasNoErrors();

        $admin->refresh();
        $this->assertSame('Renamed Admin', $admin->name);
        $this->assertSame(UserRole::Admin, $admin->role);
    }

    public function test_the_last_admin_still_cannot_be_deactivated(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->delete("/users/{$admin->id}")->assertForbidden();

        $this->assertFalse($admin->fresh()->trashed());
    }

    // ---------------------------------------------------------- user profile

    public function test_an_admin_can_open_any_users_profile_from_the_users_page(): void
    {
        $admin = $this->admin();
        $staff = User::factory()->create(['name' => 'Awa Touray', 'phone_number' => '+220835190122']);

        $this->actingAs($admin)->get('/users')
            ->assertSee(route('users.show', $staff), false);

        $this->actingAs($admin)->get("/users/{$staff->id}")
            ->assertOk()
            ->assertSeeLivewire(ProfileDetails::class)
            ->assertSee('Awa Touray')
            ->assertSee('User details')
            ->assertSee('Account status')
            ->assertSee('Recent activity')
            ->assertSee('Change password');
    }

    public function test_an_admins_own_entry_goes_to_their_profile_page(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->get("/users/{$admin->id}")->assertRedirect(route('profile'));
    }

    public function test_a_receptionist_cannot_open_another_users_profile(): void
    {
        $receptionist = User::factory()->create(['role' => UserRole::Receptionist]);
        $other = User::factory()->create();

        $this->actingAs($receptionist)->get("/users/{$other->id}")->assertForbidden();

        Livewire::actingAs($receptionist)
            ->test(ProfileDetails::class, ['user' => $other])
            ->assertForbidden();
    }

    public function test_an_admin_can_edit_another_users_details_and_role_from_their_profile(): void
    {
        $admin = $this->admin(['phone_number' => '+220835190111']);
        $staff = User::factory()->create(['role' => UserRole::Receptionist, 'phone_number' => '+220835190122']);

        Livewire::actingAs($admin)
            ->test(ProfileDetails::class, ['user' => $staff])
            ->call('startEditing')
            ->set('name', 'Promoted Staff')
            ->set('role', 'admin')
            ->call('save')
            ->assertHasNoErrors();

        $staff->refresh();
        $this->assertSame('Promoted Staff', $staff->name);
        $this->assertSame(UserRole::Admin, $staff->role);
        // An Admin correcting someone's details doesn't unverify them.
        $this->assertNotNull($staff->email_verified_at);
    }

    public function test_the_profile_card_also_refuses_an_admin_changing_their_own_role(): void
    {
        $admin = $this->admin(['phone_number' => '+220835190111']);
        $this->admin(['phone_number' => '+220835190112']);

        Livewire::actingAs($admin)
            ->test(ProfileDetails::class)
            ->call('startEditing')
            ->set('role', 'receptionist')
            ->call('save')
            ->assertHasErrors(['role' => 'You cannot change your own role.']);

        $this->assertSame(UserRole::Admin, $admin->fresh()->role);
    }

    // ------------------------------------------------------------- passwords

    public function test_the_edit_form_no_longer_changes_passwords(): void
    {
        $admin = $this->admin(['phone_number' => '+220835190111']);
        $staff = User::factory()->create(['phone_number' => '+220835190112']);

        $this->actingAs($admin)
            ->put("/users/{$staff->id}", $this->updatePayload($staff, ['password' => 'sneaky-new-pass']))
            ->assertSessionHasNoErrors();

        $this->assertTrue(Hash::check('password', $staff->fresh()->password));
    }

    public function test_the_users_page_has_change_password_and_no_edit_password_field(): void
    {
        $admin = $this->admin();
        User::factory()->create(['name' => 'Awa Touray']);

        $this->actingAs($admin)
            ->get('/users')
            ->assertOk()
            ->assertSee('Change password')
            ->assertDontSee('Leave blank to keep unchanged');
    }

    public function test_creating_a_user_requires_a_matching_confirmation(): void
    {
        $this->actingAs($this->admin())->post('/users', [
            'name' => 'New Staff',
            'email' => 'new@example.com',
            'phone_number' => '+220835190199',
            'password' => 'password',
            'password_confirmation' => 'different',
            'role' => 'receptionist',
        ])->assertSessionHasErrors(['password' => "The passwords don't match."]);

        $this->assertDatabaseMissing('users', ['email' => 'new@example.com']);
    }
}
