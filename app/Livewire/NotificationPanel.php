<?php

namespace App\Livewire;

use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Component;

class NotificationPanel extends Component
{
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
     * Only unread notifications — marking one read (markAsRead) is meant to
     * remove it from this list, not just re-style it in place, so the query
     * itself is the mechanism rather than client-side hide/show state.
     */
    public function getNotificationsProperty(): Collection
    {
        return auth()->user()->appNotifications()->where('read_status', false)->latest()->limit(10)->get();
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
    }

    public function markAllRead(): void
    {
        auth()->user()->appNotifications()->where('read_status', false)->update(['read_status' => true]);
    }

    public function render(): View
    {
        return view('livewire.notification-panel');
    }
}
