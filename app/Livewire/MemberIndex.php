<?php

namespace App\Livewire;

use App\Models\Member;
use Illuminate\Contracts\View\View;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\On;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class MemberIndex extends Component
{
    use WithPagination;

    #[Url(as: 'search')]
    public string $search = '';

    #[Url(as: 'status')]
    public string $status = 'all';

    public function mount(): void
    {
        Gate::authorize('viewAny', Member::class);
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingStatus(): void
    {
        if ($this->status === 'archived') {
            Gate::authorize('viewArchived', Member::class);
        }

        $this->resetPage();
    }

    #[On('member-saved')]
    #[On('member-renewed')]
    public function refreshList(): void
    {
        // Re-render happens automatically on next request; this listener's
        // only job is to exist so the dispatched event has a handler.
    }

    public function archive(int $memberId): void
    {
        $member = Member::findOrFail($memberId);
        Gate::authorize('delete', $member);
        $member->delete();
    }

    public function restore(int $memberId): void
    {
        $member = Member::withTrashed()->findOrFail($memberId);
        Gate::authorize('restore', $member);
        $member->restore();
    }

    public function getMembersProperty(): LengthAwarePaginator
    {
        return Member::query()
            ->search($this->search)
            ->when($this->status === 'active', fn ($q) => $q->active())
            ->when($this->status === 'expired', fn ($q) => $q->expired())
            ->when($this->status === 'archived', fn ($q) => $q->archived())
            ->latest()
            ->paginate(15);
    }

    public function render(): View
    {
        return view('livewire.member-index');
    }
}
