<?php

namespace App\Livewire;

use App\Exceptions\InvalidPhoneNumberException;
use App\Models\User;
use App\Services\PhoneNumberService;
use Illuminate\Auth\Access\Response;
use Illuminate\Contracts\View\View;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * A user's details card, read-only until the edit icon is pressed. Two
 * places use it:
 *  - /profile — your own details (no user passed in).
 *  - /users/{user} — an Admin viewing anyone's profile.
 *
 * Name, email and phone: editable by the user themselves or an Admin.
 * Role: only when UserPolicy::changeRole allows it (an Admin, never their
 * own, never demoting the last Admin) — re-checked on save.
 */
class ProfileDetails extends Component
{
    #[Locked]
    public int $userId;

    public bool $editing = false;

    public string $name = '';

    public string $email = '';

    public string $phone_number = '';

    public string $role = '';

    public function mount(?User $user = null): void
    {
        $target = $user?->exists ? $user : Auth::user();

        Gate::authorize('view', $target);

        $this->userId = $target->id;
        $this->fillForm();
    }

    public function startEditing(): void
    {
        $this->authorizeEdit();
        $this->resetErrorBag();
        $this->fillForm();
        $this->editing = true;
    }

    public function cancelEditing(): void
    {
        $this->resetErrorBag();
        $this->fillForm();
        $this->editing = false;
    }

    public function save(PhoneNumberService $phoneNumbers): void
    {
        $user = $this->authorizeEdit();

        // Same uniqueness scoping as UpdateUserRequest.
        $validated = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required', 'string', 'lowercase', 'email', 'max:255',
                Rule::unique('users', 'email')->whereNull('deleted_at')->ignore($user->id),
            ],
            'phone_number' => ['required', 'string', 'max:20'],
            'role' => ['required', 'in:admin,receptionist'],
        ], [
            'name.required' => __('Enter a name.'),
            'email.required' => __('Enter an email address.'),
            'phone_number.required' => __('Enter a phone number.'),
        ]);

        if ($validated['role'] !== $user->role->value) {
            $decision = Gate::inspect('changeRole', $user);

            if ($decision->denied()) {
                $this->addError('role', $decision->message());

                return;
            }
        }

        try {
            $validated['phone_number'] = $phoneNumbers->canonicalize($validated['phone_number']);
        } catch (InvalidPhoneNumberException $e) {
            $this->addError('phone_number', $e->getMessage());

            return;
        }

        $user->fill($validated);

        // Kept from Breeze for your own profile: a new email address has to
        // be verified again. An Admin correcting someone's address doesn't
        // lock that person out.
        if ($this->isSelf && $user->isDirty('email')) {
            $user->email_verified_at = null;
        }

        try {
            $user->save();
        } catch (UniqueConstraintViolationException) {
            $this->addError('phone_number', __('This phone number is already registered to another user.'));

            return;
        }

        unset($this->user);
        $this->fillForm();
        $this->editing = false;
        $this->dispatch('profile-updated', name: $user->name);
    }

    /**
     * ChangePasswordModal is a separate component, so re-read the
     * "password last changed" line and status badges when it reports a save.
     */
    #[On('password-updated')]
    public function refreshAfterPasswordChange(): void
    {
        unset($this->user, $this->passwordChangedAt);
    }

    #[Computed]
    public function user(): User
    {
        return User::findOrFail($this->userId);
    }

    #[Computed]
    public function isSelf(): bool
    {
        return $this->userId === Auth::id();
    }

    #[Computed]
    public function canEdit(): bool
    {
        return $this->isSelf || Gate::allows('update', $this->user);
    }

    /**
     * Whether the role can be changed here, and if not, why — shown under
     * the role in edit mode.
     */
    #[Computed]
    public function roleDecision(): Response
    {
        return Gate::inspect('changeRole', $this->user);
    }

    /**
     * On your own profile, the most recent successful login is the one you
     * just made — so "last login" means the one before it there.
     */
    #[Computed]
    public function lastLogin(): ?string
    {
        return $this->user->loginAttempts()
            ->where('successful', true)
            ->latest('id')
            ->skip($this->isSelf ? 1 : 0)
            ->value('created_at');
    }

    #[Computed]
    public function passwordChangedAt(): ?string
    {
        return $this->user->passwordHistories()->latest('id')->value('created_at');
    }

    public function render(): View
    {
        return view('livewire.profile-details');
    }

    private function authorizeEdit(): User
    {
        $user = $this->user;

        abort_unless($this->isSelf || Gate::allows('update', $user), 403);

        return $user;
    }

    private function fillForm(): void
    {
        $user = User::findOrFail($this->userId);

        $this->name = $user->name;
        $this->email = $user->email;
        $this->phone_number = $user->phone_number ?? '';
        $this->role = $user->role?->value ?? 'receptionist';
    }
}
