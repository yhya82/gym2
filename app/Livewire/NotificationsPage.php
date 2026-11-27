<?php

namespace App\Livewire;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * The full Notifications page — everything the bell's short dropdown
 * (NotificationPanel) doesn't show: older unread ones and the read history.
 *
 * Every query goes through the signed-in user's own appNotifications(), so
 * nobody can see or change anyone else's notifications.
 */
class NotificationsPage extends Component
{
    use WithPagination;

    private const FILTERS = ['all', 'unread', 'read'];

    #[Url]
    public string $filter = 'all';

    #[Locked]
    public int $userId;

    public function mount(): void
    {
        $this->userId = auth()->id();

        if (! in_array($this->filter, self::FILTERS, true)) {
            $this->filter = 'all';
        }
    }

    /** A new notification arrives live on the user's private channel. */
    protected function getListeners(): array
    {
        return [
            "echo-private:App.Models.User.{$this->userId},.notification.created" => '$refresh',
        ];
    }

    /** The bell's dropdown marked something read. */
    #[On('notifications-updated')]
    public function refreshFromPanel(): void
    {
        // Re-render only.
    }

    public function setFilter(string $filter): void
    {
        if (in_array($filter, self::FILTERS, true)) {
            $this->filter = $filter;
            $this->resetPage();
        }
    }

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

    public function getNotificationsProperty(): LengthAwarePaginator
    {
        return auth()->user()->appNotifications()
            ->when($this->filter === 'unread', fn ($q) => $q->where('read_status', false))
            ->when($this->filter === 'read', fn ($q) => $q->where('read_status', true))
            ->latest('id')
            ->paginate(20);
    }

    public function getUnreadCountProperty(): int
    {
        return auth()->user()->appNotifications()->where('read_status', false)->count();
    }

    public function render(): View
    {
        return view('livewire.notifications-page');
    }
}
