<?php

namespace App\Http\Controllers;

use App\Exceptions\InvalidPhoneNumberException;
use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Models\User;
use App\Services\PhoneNumberService;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class UserController extends Controller
{
    public function index(): View
    {
        $this->authorize('viewAny', User::class);

        $users = User::query()
            ->search(Request::query('search'))
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString();

        return view('users.index', compact('users'));
    }

    /**
     * A user's profile, as an Admin sees it from the Users page: the same
     * details card as /profile, plus account status and recent activity.
     * Your own entry redirects to /profile, where it already lives.
     */
    public function show(User $user): View|RedirectResponse
    {
        $this->authorize('view', $user);

        if ($user->is(Auth::user())) {
            return redirect()->route('profile');
        }

        $recentActivity = $user->auditLogs()->latest('id')->limit(10)->get();

        return view('users.show', compact('user', 'recentActivity'));
    }

    public function store(StoreUserRequest $request, PhoneNumberService $phoneNumbers): RedirectResponse
    {
        try {
            User::create([
                ...$request->safe()->except('password', 'phone_number'),
                'phone_number' => $phoneNumbers->canonicalize($request->validated('phone_number')),
                'password' => Hash::make($request->validated('password')),
            ]);
        } catch (InvalidPhoneNumberException $e) {
            throw ValidationException::withMessages(['phone_number' => $e->getMessage()]);
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['phone_number' => 'This phone number is already registered to another user.']);
        }

        return redirect()->route('users.index')->with('status', 'User created successfully.');
    }

    public function update(UpdateUserRequest $request, User $user, PhoneNumberService $phoneNumbers): RedirectResponse
    {
        $data = $request->validated();

        // Enforced here, not just by the form disabling the select: a
        // crafted request must not be able to demote the last Admin either.
        if (isset($data['role']) && $data['role'] !== $user->role->value) {
            $decision = Gate::inspect('changeRole', $user);

            if ($decision->denied()) {
                throw ValidationException::withMessages(['role' => $decision->message()]);
            }
        } else {
            unset($data['role']);
        }

        try {
            $data['phone_number'] = $phoneNumbers->canonicalize($request->validated('phone_number'));

            $user->update($data);
        } catch (InvalidPhoneNumberException $e) {
            throw ValidationException::withMessages(['phone_number' => $e->getMessage()]);
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['phone_number' => 'This phone number is already registered to another user.']);
        }

        return redirect()->route('users.index')->with('status', 'User updated successfully.');
    }

    public function destroy(User $user): RedirectResponse
    {
        $this->authorize('delete', $user);

        $user->delete();

        return redirect()->route('users.index')->with('status', "{$user->name} deactivated.");
    }
}
