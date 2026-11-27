<?php

namespace App\Livewire;

use App\Enums\MembershipStatus;
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

        // $status comes straight from the URL (#[Url]) on page load, which
        // never passes through updatingStatus() — so the "archived" filter
        // has to be checked here too, or ?status=archived showed archived
        // members to a Receptionist.
        $this->status = $this->allowedStatus($this->status);
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

    /**
     * The status filter actually applied: an unknown value, or "archived"
     * without MemberPolicy::viewArchived, falls back to "all".
     */
    private function allowedStatus(string $status): string
    {
        if (! in_array($status, ['all', 'active', 'expired', 'archived'], true)) {
            return 'all';
        }

        if ($status === 'archived' && ! Gate::allows('viewArchived', Member::class)) {
            return 'all';
        }

        return $status;
    }

    public function getMembersProperty(): LengthAwarePaginator
    {
        // Re-checked where the query is built, so no path that sets $status
        // (URL, Livewire update, anything added later) can skip it.
        $status = $this->allowedStatus($this->status);

        return Member::query()
            ->with('currentSubscription.plan')
            ->search($this->search)
            ->when($status === 'active', fn ($q) => $q->active())
            ->when($status === 'expired', fn ($q) => $q->expired())
            ->when($status === 'archived', fn ($q) => $q->archived())
            ->orderByRaw(' CASE WHEN members.status = ? THEN 0 WHEN members.status = ? THEN 1 ELSE 2 END ',
                [MembershipStatus::Active->value,
                    MembershipStatus::Expired->value, ])
            ->latest()
            ->paginate(15);
    }

    public function render(): View
    {
        return view('livewire.member-index');
    }
}
