<?php

namespace App\Livewire;

use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * The bell in the top bar: a short dropdown of the newest unread
 * notifications. Everything else — read history, filters — lives on the full
 * Notifications page (App\Livewire\NotificationsPage), linked at the bottom.
 */
class NotificationPanel extends Component
{
    /** How many unread notifications the dropdown shows before "+ N more". */
    public const SHOWN = 5;

    #[Locked]
    public int $userId;

    public function mount(): void
    {
        $this->userId = auth()->id();
    }

    /**
     * Dynamic channel name (per-user), so this can't be expressed with the
     * static #[On] attribute — matches UserNotified's private channel
     * ("App.Models.User.{id}") from Phase 8.
     */
    protected function getListeners(): array
    {
        return [
            "echo-private:App.Models.User.{$this->userId},.notification.created" => '$refresh',
        ];
    }

    /**
     * The Notifications page marks things read too; refresh the badge and
     * list when it does.
     */
    #[On('notifications-updated')]
    public function refreshFromPage(): void
    {
        // Re-render only.
    }

    /**
     * Only unread notifications — marking one read (markAsRead) is meant to
     * remove it from this list, not just re-style it in place, so the query
     * itself is the mechanism rather than client-side hide/show state.
     */
    public function getNotificationsProperty(): Collection
    {
        return auth()->user()->appNotifications()
            ->where('read_status', false)
            ->latest('id')
            ->limit(self::SHOWN)
            ->get();
    }

    public function getUnreadCountProperty(): int
    {
        return auth()->user()->appNotifications()->where('read_status', false)->count();
    }

    /**
     * Scoped through the authenticated user's own appNotifications(), not a
     * bare Notification::find() — otherwise one user could mark (and thus
     * dismiss) another user's notification just by guessing its id.
     */
    public function markAsRead(int $notificationId): void
    {
        auth()->user()->appNotifications()->whereKey($notificationId)->update(['read_status' => true]);
        $this->dispatch('notifications-updated');
    }

    public function markAllRead(): void
    {
        auth()->user()->appNotifications()->where('read_status', false)->update(['read_status' => true]);
        $this->dispatch('notifications-updated');
    }

    public function render(): View
    {
        return view('livewire.notification-panel');
    }
}
