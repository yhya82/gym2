<?php

namespace Tests\Feature;

use App\Enums\NotificationType;
use App\Enums\UserRole;
use App\Livewire\NotificationPanel;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class NotificationPanelTest extends TestCase
{
    use RefreshDatabase;

    private function makeNotification(User $user, bool $read = false): Notification
    {
        return Notification::create([
            'user_id' => $user->id,
            'type' => NotificationType::MemberCreated,
            'message' => 'Jane Doe registered successfully.',
            'read_status' => $read,
        ]);
    }

    public function test_the_panel_only_shows_unread_notifications(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $unread = $this->makeNotification($admin);
        $read = $this->makeNotification($admin, read: true);

        $component = Livewire::actingAs($admin)->test(NotificationPanel::class);

        $ids = $component->get('notifications')->pluck('id');
        $this->assertTrue($ids->contains($unread->id));
        $this->assertFalse($ids->contains($read->id));
    }

    public function test_marking_a_notification_as_read_removes_it_from_the_panel(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $notification = $this->makeNotification($admin);

        Livewire::actingAs($admin)
            ->test(NotificationPanel::class)
            ->assertSee('Jane Doe registered successfully.')
            ->call('markAsRead', $notification->id)
            ->assertDontSee('Jane Doe registered successfully.');

        $this->assertTrue($notification->fresh()->read_status);
    }

    public function test_a_user_cannot_mark_another_users_notification_as_read(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $otherUser = User::factory()->create(['role' => UserRole::Admin]);
        $notification = $this->makeNotification($otherUser);

        Livewire::actingAs($admin)
            ->test(NotificationPanel::class)
            ->call('markAsRead', $notification->id);

        $this->assertFalse($notification->fresh()->read_status);
    }

    public function test_mark_all_as_read_clears_the_panel_and_unread_count(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $this->makeNotification($admin);
        $this->makeNotification($admin);

        $component = Livewire::actingAs($admin)->test(NotificationPanel::class);
        $component->assertSet('unreadCount', 2);

        $component->call('markAllRead');

        $component->assertSet('unreadCount', 0);
        $this->assertCount(0, $component->get('notifications'));
    }
}
