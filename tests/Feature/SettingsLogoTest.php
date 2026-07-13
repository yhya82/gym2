<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Livewire\SettingsPage;
use App\Models\ApplicationSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class SettingsLogoTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_upload_a_logo(): void
    {
        Storage::fake('public');
        $admin = User::factory()->create(['role' => UserRole::Admin]);

        Livewire::actingAs($admin)
            ->test(SettingsPage::class)
            ->set('logoUpload', UploadedFile::fake()->image('logo.png'))
            ->call('save')
            ->assertHasNoErrors();

        $path = ApplicationSetting::current()->logo;
        $this->assertNotNull($path);
        Storage::disk('public')->assertExists($path);
    }

    public function test_uploading_a_new_logo_deletes_the_previous_file(): void
    {
        Storage::fake('public');
        $admin = User::factory()->create(['role' => UserRole::Admin]);

        Livewire::actingAs($admin)
            ->test(SettingsPage::class)
            ->set('logoUpload', UploadedFile::fake()->image('first.png'))
            ->call('save');

        $firstPath = ApplicationSetting::current()->logo;
        Storage::disk('public')->assertExists($firstPath);

        Livewire::actingAs($admin)
            ->test(SettingsPage::class)
            ->set('logoUpload', UploadedFile::fake()->image('second.png'))
            ->call('save');

        $secondPath = ApplicationSetting::current()->logo;
        $this->assertNotSame($firstPath, $secondPath);
        Storage::disk('public')->assertMissing($firstPath);
        Storage::disk('public')->assertExists($secondPath);
    }

    public function test_a_non_image_upload_is_rejected(): void
    {
        Storage::fake('public');
        $admin = User::factory()->create(['role' => UserRole::Admin]);

        Livewire::actingAs($admin)
            ->test(SettingsPage::class)
            ->set('logoUpload', UploadedFile::fake()->create('document.pdf', 100))
            ->call('save')
            ->assertHasErrors('logoUpload');

        $this->assertNull(ApplicationSetting::current()->logo);
    }

    public function test_admin_can_remove_the_logo(): void
    {
        Storage::fake('public');
        $admin = User::factory()->create(['role' => UserRole::Admin]);

        Livewire::actingAs($admin)
            ->test(SettingsPage::class)
            ->set('logoUpload', UploadedFile::fake()->image('logo.png'))
            ->call('save');

        $path = ApplicationSetting::current()->logo;

        Livewire::actingAs($admin)
            ->test(SettingsPage::class)
            ->call('removeLogo');

        $this->assertNull(ApplicationSetting::current()->logo);
        Storage::disk('public')->assertMissing($path);
    }

    public function test_receptionist_cannot_access_settings_page_component(): void
    {
        $receptionist = User::factory()->create(['role' => UserRole::Receptionist]);

        Livewire::actingAs($receptionist)
            ->test(SettingsPage::class)
            ->assertForbidden();
    }
}
