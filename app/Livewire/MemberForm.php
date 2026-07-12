<?php

namespace App\Livewire;

use App\Exceptions\InvalidPhoneNumberException;
use App\Exceptions\PaymentExceedsBalanceException;
use App\Models\Member;
use App\Models\Plan;
use App\Services\MemberRegistrationService;
use App\Services\PhoneNumberService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\On;
use Livewire\Component;

class MemberForm extends Component
{
    /**
     * Serves two contexts (per the plan: "MemberForm (create/edit modal)"):
     * standalone full-page create (mounted directly via the members.create
     * route, memberId null) and an edit modal embedded in the member index
     * (opened by the "edit-member" event). Editing only touches name/phone —
     * plan/payment only apply at creation — so the same component just
     * shows a smaller field set in edit mode rather than needing a sibling
     * component for what's fundamentally the same "member identity" form.
     */
    public ?int $memberId = null;

    public bool $standalone = false;

    public string $full_name = '';

    public string $phone_number = '';

    public ?int $plan_id = null;

    public string $start_date = '';

    public string $payment_amount = '';

    public function mount(): void
    {
        $this->standalone = request()->routeIs('members.create');
        $this->start_date = now()->toDateString();

        if ($this->standalone) {
            Gate::authorize('create', Member::class);
        }
    }

    #[On('edit-member')]
    public function loadForEdit(int $memberId): void
    {
        $member = Member::findOrFail($memberId);
        Gate::authorize('update', $member);

        $this->resetErrorBag();
        $this->memberId = $member->id;
        $this->full_name = $member->full_name;
        $this->phone_number = $member->phone_number;
        $this->dispatch('open-modal', name: 'member-form-modal');
    }

    public function save(MemberRegistrationService $registration, PhoneNumberService $phoneNumbers): void
    {
        if ($this->memberId) {
            $this->updateExisting($phoneNumbers);

            return;
        }

        $this->createNew($registration);
    }

    private function updateExisting(PhoneNumberService $phoneNumbers): void
    {
        $member = Member::findOrFail($this->memberId);
        Gate::authorize('update', $member);

        $this->validate([
            'full_name' => ['required', 'string', 'max:255'],
            'phone_number' => ['required', 'string', 'max:20'],
        ]);

        try {
            $member->update([
                'full_name' => $this->full_name,
                'phone_number' => $phoneNumbers->canonicalize($this->phone_number),
            ]);
        } catch (InvalidPhoneNumberException $e) {
            $this->addError('phone_number', $e->getMessage());

            return;
        }

        $this->dispatch('close-modal', name: 'member-form-modal');
        $this->dispatch('member-saved');
    }

    private function createNew(MemberRegistrationService $registration): void
    {
        Gate::authorize('create', Member::class);

        $validated = $this->validate([
            'full_name' => ['required', 'string', 'max:255'],
            'phone_number' => ['required', 'string', 'max:20'],
            'plan_id' => ['required', 'integer', 'exists:plans,id'],
            'start_date' => ['required', 'date'],
            'payment_amount' => ['required', 'numeric', 'gt:0'],
        ]);

        $plan = Plan::findOrFail($validated['plan_id']);

        try {
            $member = $registration->register(
                $validated['full_name'],
                $validated['phone_number'],
                $plan,
                Carbon::parse($validated['start_date']),
                (string) $validated['payment_amount'],
                auth()->user(),
            );
        } catch (InvalidPhoneNumberException $e) {
            $this->addError('phone_number', $e->getMessage());

            return;
        } catch (PaymentExceedsBalanceException $e) {
            $this->addError('payment_amount', $e->getMessage());

            return;
        }

        $this->redirect(route('members.show', $member), navigate: true);
    }

    public function getPlansProperty()
    {
        return Plan::query()->orderBy('plan_name')->get();
    }

    public function render(): View
    {
        return view('livewire.member-form');
    }
}
