<?php

namespace App\Livewire;

use App\Models\Member;
use Illuminate\Contracts\View\View;
use Livewire\Component;

class ReceptionistDashboard extends Component
{
    public array $stats = [];

    public function mount(): void
    {
        $this->refreshStats();
    }

    /**
     * Only the member-count channel — no dashboard.revenue listener at all,
     * since revenue is never sent to a Receptionist's browser (§12.2, and
     * the channel-authorization boundary from Phase 8).
     */
    protected function getListeners(): array
    {
        return [
            'echo-private:dashboard,.stats.updated' => 'onStatsUpdated',
        ];
    }

    public function onStatsUpdated(array $event): void
    {
        $this->stats = [...$this->stats, ...$event];
    }

    private function refreshStats(): void
    {
        $this->stats = [
            'total_members' => Member::count(),
            'active_members' => Member::active()->count(),
            'expired_members' => Member::expired()->count(),
        ];
    }

    public function render(): View
    {
        return view('livewire.receptionist-dashboard');
    }
}
