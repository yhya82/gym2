<?php

namespace App\Livewire;

use App\Models\User;
use App\Rules\NotInPasswordHistory;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * One "Change password" modal for both places a password can be changed:
 *  - Profile: your own password (opened with no user id).
 *  - Users page: an Admin changing someone else's (opened with their id).
 *    That user must then pick their own password at their next login, so
 *    the Admin never knows the password actually in use.
 *
 * Who may change whose password is UserPolicy::changePassword, checked on
 * open and again on save.
 */
class ChangePasswordModal extends Component
{
    // Locked: the browser must not be able to swap in a different target
    // between opening the modal and saving it.
    #[Locked]
    public ?int $userId = null;

    #[Locked]
    public string $userName = '';

    public string $password = '';

    public string $password_confirmation = '';

    #[On('open-change-password')]
    public function open(?int $userId = null): void
    {
        $target = $this->resolveTarget($userId);

        $this->resetErrorBag();
        $this->reset('password', 'password_confirmation');
        $this->userId = $target->id;
        $this->userName = $target->name;

        $this->dispatch('open-modal', 'change-password');
    }

    public function save(): void
    {
        $target = $this->resolveTarget($this->userId);

        $this->validate([
            'password' => ['required', 'string', 'confirmed', Password::defaults(), new NotInPasswordHistory($target)],
        ], [
            'password.required' => __('Enter a new password.'),
            'password.confirmed' => __("The passwords don't match."),
        ], [
            'password' => __('new password'),
        ]);

        $forOtherUser = $target->id !== Auth::id();

        // UserObserver writes the audit entry ("password-set" for an Admin
        // changing someone else's, "password-changed" for your own).
        $target->forceFill([
            'password' => Hash::make($this->password),
            'must_change_password' => $forOtherUser,
        ])->save();

        $this->reset('password', 'password_confirmation');
        $this->dispatch('close-modal', 'change-password');
        $this->dispatch('password-updated', message: $forOtherUser
            ? __(':name\'s password was changed. They will be asked to choose a new one when they next log in.', ['name' => $target->name])
            : __('Your password was changed.'));
    }

    public function render(): View
    {
        return view('livewire.change-password-modal', [
            'isSelf' => $this->userId === null || $this->userId === Auth::id(),
        ]);
    }

    private function resolveTarget(?int $userId): User
    {
        // Your own password goes through the SAME object the auth guard
        // holds: AuthenticateSession re-reads that object's password hash at
        // the end of the request to keep this session valid. A separately
        // loaded copy would leave the guard on the old hash and sign you out.
        $target = ($userId === null || $userId === Auth::id())
            ? Auth::user()
            : User::findOrFail($userId);

        Gate::authorize('changePassword', $target);

        return $target;
    }
}
