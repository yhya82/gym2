<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Livewire\ChangePasswordModal;
use App\Livewire\ProfileDetails;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ProfileTest extends TestCase
{
    use RefreshDatabase;

    private function user(array $attributes = []): User
    {
        return User::factory()->create(['phone_number' => '+220835190199', ...$attributes]);
    }

    public function test_profile_page_shows_the_details_card_and_change_password(): void
    {
        $user = $this->user(['name' => 'Awa Touray']);

        $this->actingAs($user)
            ->get('/profile')
            ->assertOk()
            ->assertSeeLivewire(ProfileDetails::class)
            ->assertSeeLivewire(ChangePasswordModal::class)
            ->assertSee('Awa Touray')
            ->assertSee('+220 835190199')
            ->assertSee('Change password');
    }

    public function test_details_are_read_only_until_the_edit_icon_is_pressed(): void
    {
        Livewire::actingAs($this->user())
            ->test(ProfileDetails::class)
            ->assertSet('editing', false)
            ->assertDontSee('Save changes')
            ->call('startEditing')
            ->assertSet('editing', true)
            ->assertSee('Save changes');
    }

    public function test_profile_information_can_be_updated(): void
    {
        $user = $this->user();

        Livewire::actingAs($user)
            ->test(ProfileDetails::class)
            ->call('startEditing')
            ->set('name', 'Test User')
            ->set('email', 'test@example.com')
            ->set('phone_number', '835190188')
            ->call('save')
            ->assertHasNoErrors()
            ->assertSet('editing', false);

        $user->refresh();

        $this->assertSame('Test User', $user->name);
        $this->assertSame('test@example.com', $user->email);
        $this->assertSame('+220835190188', $user->phone_number);
        $this->assertNull($user->email_verified_at);
    }

    public function test_email_verification_status_is_unchanged_when_the_email_address_is_unchanged(): void
    {
        $user = $this->user();

        Livewire::actingAs($user)
            ->test(ProfileDetails::class)
            ->call('startEditing')
            ->set('name', 'Test User')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertNotNull($user->refresh()->email_verified_at);
    }

    public function test_name_email_and_phone_are_required(): void
    {
        $user = $this->user(['name' => 'Kept Name']);

        Livewire::actingAs($user)
            ->test(ProfileDetails::class)
            ->assertSee('Name')
            ->call('startEditing')
            ->assertSeeHtml('id="pd-name" required')
            ->assertSeeHtml('id="pd-email" required')
            ->assertSeeHtml('id="pd-phone" required')
            ->set('name', '')
            ->set('email', '')
            ->set('phone_number', '')
            ->call('save')
            ->assertHasErrors([
                'name' => 'Enter a name.',
                'email' => 'Enter an email address.',
                'phone_number' => 'Enter a phone number.',
            ])
            ->assertSet('editing', true);

        $this->assertSame('Kept Name', $user->fresh()->name);
    }

    public function test_an_invalid_phone_number_is_rejected(): void
    {
        Livewire::actingAs($this->user())
            ->test(ProfileDetails::class)
            ->call('startEditing')
            ->set('phone_number', '123')
            ->call('save')
            ->assertHasErrors('phone_number')
            ->assertSet('editing', true);
    }

    /**
     * The card has no role property at all, so there's nothing a crafted
     * Livewire request could set.
     */
    public function test_users_cannot_change_their_own_role_from_the_profile(): void
    {
        $user = $this->user(['role' => UserRole::Receptionist]);

        try {
            Livewire::actingAs($user)
                ->test(ProfileDetails::class)
                ->call('startEditing')
                ->set('role', 'admin')
                ->call('save');
        } catch (\Throwable) {
            // Expected: Livewire refuses to set a property that doesn't exist.
        }

        $this->assertSame(UserRole::Receptionist, $user->fresh()->role);
    }

    /**
     * Deactivating staff is Admin-only (§3.2) — the profile page offers no
     * self-service account deletion.
     */
    public function test_the_profile_page_has_no_self_service_account_deletion(): void
    {
        $user = $this->user();

        $this->actingAs($user)
            ->get('/profile')
            ->assertOk()
            ->assertDontSee('Delete Account');

        $this->assertFalse($user->fresh()->trashed());
    }
}
