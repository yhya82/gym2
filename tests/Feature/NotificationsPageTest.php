<?php

namespace Tests\Feature;

use App\Enums\NotificationType;
use App\Enums\UserRole;
use App\Livewire\NotificationPanel;
use App\Livewire\NotificationsPage;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class NotificationsPageTest extends TestCase
{
    use RefreshDatabase;

    private function notify(User $user, string $message, bool $read = false): Notification
    {
        return Notification::create([
            'user_id' => $user->id,
            'type' => NotificationType::MembershipExpired,
            'message' => $message,
            'read_status' => $read,
        ]);
    }

    // ------------------------------------------------------- bell dropdown

    public function test_the_dropdown_shows_only_the_five_newest_unread_and_how_many_more(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        foreach (range(1, 8) as $i) {
            $this->notify($admin, "Unread {$i}");
        }
        $this->notify($admin, 'Already read', read: true);

        $component = Livewire::actingAs($admin)->test(NotificationPanel::class);

        $shown = $component->instance()->notifications->pluck('message')->all();
        $this->assertSame(['Unread 8', 'Unread 7', 'Unread 6', 'Unread 5', 'Unread 4'], $shown);

        $component
            ->assertSee('+ 3 more unread')
            ->assertSee('View all notifications')
            ->assertDontSee('Already read');
    }

    public function test_the_dropdown_has_no_more_line_when_everything_fits(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $this->notify($admin, 'Only one');

        Livewire::actingAs($admin)->test(NotificationPanel::class)
            ->assertDontSee('more unread')
            ->assertSee('View all notifications');
    }

    // ------------------------------------------------------- full page

    public function test_the_page_lists_read_and_unread_and_filters_them(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $this->notify($admin, 'Fresh one');
        $this->notify($admin, 'Seen one', read: true);

        $this->actingAs($admin)->get('/notifications')
            ->assertOk()
            ->assertSee(['Fresh one', 'Seen one', '1 unread notification.']);

        Livewire::actingAs($admin)->test(NotificationsPage::class)
            ->call('setFilter', 'unread')->assertSee('Fresh one')->assertDontSee('Seen one')
            ->call('setFilter', 'read')->assertSee('Seen one')->assertDontSee('Fresh one')
            ->call('setFilter', 'nonsense')->assertSet('filter', 'read');
    }

    public function test_the_filter_can_be_opened_from_the_url(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $this->notify($admin, 'Fresh one');
        $this->notify($admin, 'Seen one', read: true);

        $this->actingAs($admin)->get('/notifications?filter=unread')
            ->assertSee('Fresh one')
            ->assertDontSee('Seen one');
    }

    public function test_marking_read_on_the_page_updates_the_bell(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $note = $this->notify($admin, 'Fresh one');

        Livewire::actingAs($admin)->test(NotificationsPage::class)
            ->call('markAsRead', $note->id)
            ->assertDispatched('notifications-updated');

        $this->assertTrue($note->fresh()->read_status);

        $this->notify($admin, 'Another');
        Livewire::actingAs($admin)->test(NotificationsPage::class)
            ->call('markAllRead')
            ->assertSee("You're all caught up.");
    }

    public function test_nobody_sees_or_marks_anyone_elses_notifications(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $other = User::factory()->create(['role' => UserRole::Admin]);
        $theirs = $this->notify($other, 'Their private note');

        $this->actingAs($admin)->get('/notifications')->assertDontSee('Their private note');

        Livewire::actingAs($admin)->test(NotificationsPage::class)->call('markAsRead', $theirs->id);
        $this->assertFalse($theirs->fresh()->read_status);
    }

    public function test_the_page_needs_a_login_but_no_particular_role(): void
    {
        $this->get('/notifications')->assertRedirect('/login');

        $this->actingAs(User::factory()->create(['role' => UserRole::Receptionist]))
            ->get('/notifications')
            ->assertOk();
    }
}
