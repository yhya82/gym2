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
        Storage::fake('s3');
        $admin = User::factory()->create(['role' => UserRole::Admin]);

        Livewire::actingAs($admin)
            ->test(SettingsPage::class)
            ->set('logoUpload', UploadedFile::fake()->image('logo.png', 200, 200))
            ->call('save')
            ->assertHasNoErrors();

        $path = ApplicationSetting::current()->logo;
        $this->assertNotNull($path);
        Storage::disk('s3')->assertExists($path);
    }

    public function test_uploading_a_new_logo_deletes_the_previous_file(): void
    {
        Storage::fake('s3');
        $admin = User::factory()->create(['role' => UserRole::Admin]);

        Livewire::actingAs($admin)
            ->test(SettingsPage::class)
            ->set('logoUpload', UploadedFile::fake()->image('first.png', 200, 200))
            ->call('save');

        $firstPath = ApplicationSetting::current()->logo;
        Storage::disk('s3')->assertExists($firstPath);

        Livewire::actingAs($admin)
            ->test(SettingsPage::class)
            ->set('logoUpload', UploadedFile::fake()->image('second.png', 200, 200))
            ->call('save');

        $secondPath = ApplicationSetting::current()->logo;
        $this->assertNotSame($firstPath, $secondPath);
        Storage::disk('s3')->assertMissing($firstPath);
        Storage::disk('s3')->assertExists($secondPath);
    }

    public function test_a_non_image_upload_is_rejected(): void
    {
        Storage::fake('s3');
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
        Storage::fake('s3');
        $admin = User::factory()->create(['role' => UserRole::Admin]);

        Livewire::actingAs($admin)
            ->test(SettingsPage::class)
            ->set('logoUpload', UploadedFile::fake()->image('logo.png', 200, 200))
            ->call('save');

        $path = ApplicationSetting::current()->logo;

        Livewire::actingAs($admin)
            ->test(SettingsPage::class)
            ->call('removeLogo');

        $this->assertNull(ApplicationSetting::current()->logo);
        Storage::disk('s3')->assertMissing($path);
    }

    public function test_the_logo_is_stored_under_a_random_name_not_the_uploaders(): void
    {
        Storage::fake('s3');
        $admin = User::factory()->create(['role' => UserRole::Admin]);

        Livewire::actingAs($admin)
            ->test(SettingsPage::class)
            ->set('logoUpload', UploadedFile::fake()->image('My Gym Logo (final).png', 200, 200))
            ->call('save')
            ->assertHasNoErrors();

        $this->assertMatchesRegularExpression('#^logos/[A-Za-z0-9]{40}\.png$#', ApplicationSetting::current()->logo);
    }

    /**
     * @return array<string, array{0: UploadedFile, 1: string}>
     */
    public static function rejectedUploads(): array
    {
        return [
            'svg (can carry script)' => [UploadedFile::fake()->createWithContent('logo.svg', '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>'), 'The logo must be an image.'],
            'gif (not an allowed type)' => [UploadedFile::fake()->image('logo.gif', 200, 200), 'The logo must be a PNG, JPG or WebP image.'],
            'over 2 MB' => [UploadedFile::fake()->image('logo.png', 200, 200)->size(3000), 'The logo must be 2 MB or smaller.'],
            'too small' => [UploadedFile::fake()->image('logo.png', 16, 16), 'The logo must be between 32×32 and 2000×2000 pixels.'],
            'too large' => [UploadedFile::fake()->image('logo.png', 2500, 400), 'The logo must be between 32×32 and 2000×2000 pixels.'],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('rejectedUploads')]
    public function test_invalid_logo_uploads_are_rejected_with_a_clear_message(UploadedFile $file, string $message): void
    {
        Storage::fake('s3');
        $admin = User::factory()->create(['role' => UserRole::Admin]);

        Livewire::actingAs($admin)
            ->test(SettingsPage::class)
            ->set('logoUpload', $file)
            ->call('save')
            ->assertHasErrors(['logoUpload' => $message]);

        $this->assertNull(ApplicationSetting::current()->logo);
    }

    public function test_a_storage_failure_keeps_the_existing_logo_and_says_so(): void
    {
        Storage::fake('s3');
        $admin = User::factory()->create(['role' => UserRole::Admin]);

        Livewire::actingAs($admin)
            ->test(SettingsPage::class)
            ->set('logoUpload', UploadedFile::fake()->image('first.png', 200, 200))
            ->call('save');
        $existing = ApplicationSetting::current()->logo;

        // Now make storage unusable — the real disk, with no credentials.
        Storage::forgetDisk('s3');
        config(['filesystems.disks.s3.key' => null, 'filesystems.disks.s3.secret' => null, 'filesystems.disks.s3.bucket' => null]);

        Livewire::actingAs($admin)
            ->test(SettingsPage::class)
            ->set('logoUpload', UploadedFile::fake()->image('second.png', 200, 200))
            ->call('save')
            ->assertHasErrors('logoUpload');

        $this->assertSame($existing, ApplicationSetting::current()->logo);
    }

    public function test_the_settings_form_endpoint_cannot_point_the_logo_at_an_arbitrary_path(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);

        $this->actingAs($admin)->put('/settings', [
            'application_name' => 'Gym',
            'logo' => 'backups/gym2_db-latest.sql.gz',
            'currency' => 'GMD',
            'timezone' => 'Africa/Banjul',
            'default_theme' => 'light',
        ])->assertRedirect();

        $this->assertNull(ApplicationSetting::current()->logo);
    }

    public function test_temporary_uploads_are_kept_off_the_public_disk(): void
    {
        $this->assertSame('local', config('livewire.temporary_file_upload.disk'));
        $this->assertNotContains('svg', config('livewire.temporary_file_upload.preview_mimes'));
    }

    public function test_receptionist_cannot_access_settings_page_component(): void
    {
        $receptionist = User::factory()->create(['role' => UserRole::Receptionist]);

        Livewire::actingAs($receptionist)
            ->test(SettingsPage::class)
            ->assertForbidden();
    }
}
