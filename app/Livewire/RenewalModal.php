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

        // Prefill with the plan they're already on — most renewals keep the
        // same plan, so this saves staff a redundant re-selection. Only
        // prefills if that plan is still active; an archived plan isn't in
        // the dropdown at all (getPlansProperty), so carrying its id over
        // would leave the select showing nothing chosen anyway.
        $previousPlanId = $member->currentSubscription?->plan_id;
        $this->plan_id = $previousPlanId && Plan::query()->whereKey($previousPlanId)->exists()
            ? $previousPlanId
            : null;

        $this->start_date = now()->toDateString();
        $this->payment_amount = '';
        // Positional arg, not named — see MemberForm::loadForEdit() for why.
        $this->dispatch('open-modal', 'renewal-modal');
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

        $this->dispatch('close-modal', 'renewal-modal');
        $this->dispatch('member-renewed');
    }

    public function render(): View
    {
        return view('livewire.renewal-modal');
    }
}
