<?php

namespace App\Livewire;

use App\Exceptions\PaymentExceedsBalanceException;
use App\Models\Member;
use App\Models\Plan;
use App\Services\MembershipRenewalService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\On;
use Livewire\Component;

class RenewalModal extends Component
{
    public ?int $memberId = null;

    public string $memberName = '';

    public string $memberStatus = '';

    public ?int $plan_id = null;

    public string $start_date = '';

    public string $payment_amount = '';

    #[On('renew-member')]
    public function loadMember(int $memberId): void
    {
        $member = Member::findOrFail($memberId);
        Gate::authorize('renew', $member);

        $this->resetErrorBag();
        $this->memberId = $member->id;
        $this->memberName = $member->full_name;
        $this->memberStatus = $member->status->value;
        $this->plan_id = null;
        $this->start_date = now()->toDateString();
        $this->payment_amount = '';
        $this->dispatch('open-modal', name: 'renewal-modal');
    }

    public function getSelectedPlanProperty(): ?Plan
    {
        return $this->plan_id ? Plan::find($this->plan_id) : null;
    }

    public function getNewExpiryDateProperty(): ?string
    {
        if (! $this->selectedPlan || ! $this->start_date) {
            return null;
        }

        return Carbon::parse($this->start_date)->addDays($this->selectedPlan->duration_days)->format('M j, Y');
    }

    public function getPlansProperty()
    {
        return Plan::query()->orderBy('plan_name')->get();
    }

    public function renew(MembershipRenewalService $renewal): void
    {
        $member = Member::findOrFail($this->memberId);
        Gate::authorize('renew', $member);

        $validated = $this->validate([
            'plan_id' => ['required', 'integer', 'exists:plans,id'],
            'start_date' => ['required', 'date'],
            'payment_amount' => ['required', 'numeric', 'gt:0'],
        ]);

        $plan = Plan::findOrFail($validated['plan_id']);

        try {
            $renewal->renew(
                $member,
                $plan,
                Carbon::parse($validated['start_date']),
                (string) $validated['payment_amount'],
                auth()->user(),
            );
        } catch (PaymentExceedsBalanceException $e) {
            $this->addError('payment_amount', $e->getMessage());

            return;
        }

        $this->dispatch('close-modal', name: 'renewal-modal');
        $this->dispatch('member-renewed');
    }

    public function render(): View
    {
        return view('livewire.renewal-modal');
    }
}
