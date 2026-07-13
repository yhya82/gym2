<?php

namespace App\Http\Controllers;

use App\Exceptions\PaymentExceedsBalanceException;
use App\Exceptions\SubscriptionNotActiveException;
use App\Http\Requests\StorePaymentRequest;
use App\Models\Payment;
use App\Models\Subscription;
use App\Services\PaymentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class PaymentController extends Controller
{
    /**
     * Financial reports/history — Admin-only (§3.2 restricts "Access
     * financial reports"; the Receptionist sidebar has no Payments tab, §6).
     */
    public function index(): View
    {
        $this->authorize('viewAny', Payment::class);

        $payments = Payment::query()
            ->search(Request::query('search'))
            ->with(['member', 'receivedBy'])
            ->latest('payment_date')
            ->paginate(15)
            ->withQueryString();

        return view('payments.index', compact('payments'));
    }

    /**
     * Records an additional ("top-up") payment against an existing
     * subscription — both roles can do this (§3.2 — "Record payments").
     */
    public function store(StorePaymentRequest $request, PaymentService $payments): RedirectResponse
    {
        $subscription = Subscription::findOrFail($request->validated('subscription_id'));

        try {
            $payments->record(
                $subscription,
                (string) $request->validated('amount'),
                Auth::user(),
                Request::date('payment_date'),
            );
        } catch (PaymentExceedsBalanceException|SubscriptionNotActiveException $e) {
            throw ValidationException::withMessages(['amount' => $e->getMessage()]);
        }

        return redirect()->route('members.show', $subscription->member_id)->with('status', 'Payment recorded successfully.');
    }
}
