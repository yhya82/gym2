<?php

namespace App\Http\Controllers;

use App\Exceptions\InvalidPhoneNumberException;
use App\Exceptions\PaymentExceedsBalanceException;
use App\Http\Requests\StoreMemberRequest;
use App\Http\Requests\UpdateMemberRequest;
use App\Models\Member;
use App\Models\Plan;
use App\Services\MemberRegistrationService;
use App\Services\PhoneNumberService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request as RequestFacade;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class MemberController extends Controller
{
    /**
     * Data fetching/filtering now lives in the MemberIndex Livewire
     * component (search/pagination need to be reactive, not full-page
     * reloads) — this just gates the route and renders the page.
     */
    public function index(): View
    {
        $this->authorize('viewAny', Member::class);

        return view('members.index');
    }

    public function create(): View
    {
        $this->authorize('create', Member::class);

        return view('members.create');
    }

    public function store(StoreMemberRequest $request, MemberRegistrationService $registration): RedirectResponse
    {
        $plan = Plan::findOrFail($request->validated('plan_id'));

        try {
            $member = $registration->register(
                $request->validated('full_name'),
                $request->validated('phone_number'),
                $plan,
                RequestFacade::date('start_date') ?? now(),
                (string) $request->validated('payment_amount'),
                Auth::user(),
            );
        } catch (InvalidPhoneNumberException $e) {
            throw ValidationException::withMessages(['phone_number' => $e->getMessage()]);
        } catch (PaymentExceedsBalanceException $e) {
            throw ValidationException::withMessages(['payment_amount' => $e->getMessage()]);
        }

        return redirect()->route('members.show', $member)->with('status', 'Member created successfully.');
    }

    public function show(Member $member): View
    {
        $this->authorize('view', $member);

        $member->load(['subscriptions.plan', 'payments.receivedBy', 'createdBy']);

        return view('members.show', compact('member'));
    }

    public function update(UpdateMemberRequest $request, Member $member, PhoneNumberService $phoneNumbers): RedirectResponse
    {
        try {
            $member->update([
                'full_name' => $request->validated('full_name'),
                'phone_number' => $phoneNumbers->canonicalize($request->validated('phone_number')),
            ]);
        } catch (InvalidPhoneNumberException $e) {
            throw ValidationException::withMessages(['phone_number' => $e->getMessage()]);
        }

        return redirect()->route('members.show', $member)->with('status', 'Member information saved.');
    }

    public function destroy(Member $member): RedirectResponse
    {
        $this->authorize('delete', $member);

        $member->delete();

        return redirect()->route('members.index')->with('status', "{$member->full_name} archived.");
    }

    public function restore(int $member): RedirectResponse
    {
        $member = Member::withTrashed()->findOrFail($member);

        $this->authorize('restore', $member);

        $member->restore();

        return redirect()->route('members.show', $member)->with('status', "{$member->full_name} restored.");
    }
}
