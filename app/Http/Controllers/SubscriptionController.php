<?php

namespace App\Http\Controllers;

use App\Exceptions\PaymentExceedsBalanceException;
use App\Http\Requests\RenewSubscriptionRequest;
use App\Models\Member;
use App\Models\Plan;
use App\Services\MembershipRenewalService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;
use Illuminate\Validation\ValidationException;

class SubscriptionController extends Controller
{
    /**
     * Renewal-specific action, kept off MemberController: it creates a new
     * subscription (never edits the member's own fields) per the Renewal
     * Rules — a distinct enough operation to warrant its own controller.
     */
    public function renew(RenewSubscriptionRequest $request, Member $member, MembershipRenewalService $renewal): RedirectResponse
    {
        $plan = Plan::findOrFail($request->validated('plan_id'));

        try {
            $renewal->renew(
                $member,
                $plan,
                Request::date('start_date') ?? now(),
                (string) $request->validated('payment_amount'),
                Auth::user(),
            );
        } catch (PaymentExceedsBalanceException $e) {
            throw ValidationException::withMessages(['payment_amount' => $e->getMessage()]);
        }

        return redirect()->route('members.show', $member)->with('status', "{$member->full_name} renewed {$plan->plan_name} membership.");
    }
}
