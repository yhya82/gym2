<?php

namespace App\Http\Controllers;

use App\Exceptions\InvalidPhoneNumberException;
use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Models\User;
use App\Services\PhoneNumberService;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\RedirectResponse;
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
        $data = $request->safe()->except('password');

        if ($request->filled('password')) {
            $data['password'] = Hash::make($request->validated('password'));
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
