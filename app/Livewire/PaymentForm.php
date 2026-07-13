<?php

namespace App\Livewire;

use App\Exceptions\PaymentExceedsBalanceException;
use App\Exceptions\SubscriptionNotActiveException;
use App\Models\Member;
use App\Models\Payment;
use App\Models\Subscription;
use App\Services\PaymentService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;

class PaymentForm extends Component
{
    public Member $member;

    public string $amount = '';

    public function mount(Member $member): void
    {
        $this->member = $member;
    }

    public function getSubscriptionProperty(): ?Subscription
    {
        return $this->member->currentSubscription;
    }

    /**
     * Records an additional ("top-up") payment against the member's current
     * subscription — separate from the first payment bundled into
     * registration/renewal (§9 — partial payments).
     */
    public function record(PaymentService $payments): void
    {
        Gate::authorize('create', Payment::class);

        $subscription = $this->subscription;

        if (! $subscription) {
            $this->addError('amount', __('This member has no subscription to record a payment against.'));

            return;
        }

        $this->validate(['amount' => ['required', 'numeric', 'gt:0']]);

        try {
            $payments->record($subscription, (string) $this->amount, auth()->user());
        } catch (PaymentExceedsBalanceException|SubscriptionNotActiveException $e) {
            $this->addError('amount', $e->getMessage());

            return;
        }

        // PaymentService updates a separately-fetched Subscription instance
        // internally, not this one — $this->member's cached currentSubscription
        // relation would still show the pre-payment balance otherwise.
        $this->member->unsetRelation('currentSubscription');

        $this->amount = '';
        $this->dispatch('payment-recorded');
    }

    public function render(): View
    {
        return view('livewire.payment-form');
    }
}
