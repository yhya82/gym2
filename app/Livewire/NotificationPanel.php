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

    public function getNotificationsProperty(): Collection
    {
        return auth()->user()->appNotifications()->latest()->limit(10)->get();
    }

    public function getUnreadCountProperty(): int
    {
        return auth()->user()->appNotifications()->where('read_status', false)->count();
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
