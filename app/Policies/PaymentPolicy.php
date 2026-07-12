<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\Payment;
use App\Models\User;

class PaymentPolicy
{
    /**
     * The standalone Payments/financial-reports page is Admin-only — the
     * Receptionist sidebar has no Payments tab at all (§6), and "Access
     * financial reports" is explicitly restricted (§3.2).
     */
    public function viewAny(User $user): bool
    {
        return $user->role === UserRole::Admin;
    }

    /**
     * Viewing an individual payment in the context of a member's profile
     * (activity timeline, §19.3) is not a financial report — both roles can
     * see it there, since both roles manage members and can record payments.
     */
    public function view(User $user, Payment $payment): bool
    {
        return true;
    }

    /**
     * Recording a payment happens inline during member creation/renewal,
     * which both roles can do (§3.2 Allowed Actions: "Record payments").
     */
    public function create(User $user): bool
    {
        return true;
    }

    // No update/delete/restore/forceDelete: payment history is immutable
    // (Rule 3 — cannot be deleted) and nothing in the spec allows editing a
    // payment once recorded.
}
