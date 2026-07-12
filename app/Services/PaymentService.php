<?php

namespace App\Services;

use App\Events\PaymentRecorded;
use App\Exceptions\PaymentExceedsBalanceException;
use App\Models\Payment;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class PaymentService
{
    /**
     * Records a payment against a subscription and keeps
     * subscriptions.amount_paid/balance in sync in the same transaction.
     *
     * The app-level check here exists purely for a fast, friendly validation
     * error. The real, race-safe guarantee is the trg_payments_before_insert
     * database trigger (it locks the subscription row via FOR UPDATE), which
     * is why this method also locks the row before reading amount_paid — two
     * concurrent payments against the same subscription must be serialized,
     * or both could read the same stale total and jointly overshoot
     * plan_price before either one's write is visible to the other.
     *
     * @throws PaymentExceedsBalanceException if the amount would push
     *                                         amount_paid past plan_price,
     *                                         whether caught here or by the
     *                                         trigger.
     */
    public function record(
        Subscription $subscription,
        string $amount,
        User $staff,
        ?Carbon $paymentDate = null,
    ): Payment {
        if (bccomp($amount, '0', 2) <= 0) {
            throw new \InvalidArgumentException('Payment amount must be greater than zero.');
        }

        return DB::transaction(function () use ($subscription, $amount, $staff, $paymentDate) {
            $locked = Subscription::query()->lockForUpdate()->findOrFail($subscription->id);

            $projectedTotal = bcadd($locked->amount_paid, $amount, 2);

            if (bccomp($projectedTotal, $locked->plan_price, 2) > 0) {
                throw new PaymentExceedsBalanceException(
                    "Payment of {$amount} would exceed the remaining balance on this subscription."
                );
            }

            try {
                $payment = Payment::create([
                    'member_id' => $locked->member_id,
                    'subscription_id' => $locked->id,
                    'amount' => $amount,
                    'payment_date' => $paymentDate ?? now(),
                    'received_by' => $staff->id,
                ]);
            } catch (QueryException $e) {
                if (str_contains($e->getMessage(), 'Payment exceeds remaining balance')) {
                    throw new PaymentExceedsBalanceException(
                        'Payment exceeds the remaining balance on this subscription.',
                        previous: $e,
                    );
                }

                throw $e;
            }

            $locked->update([
                'amount_paid' => $projectedTotal,
                'balance' => bcsub($locked->plan_price, $projectedTotal, 2),
            ]);

            // record() is sometimes called nested inside a larger transaction
            // (member registration, renewal) via a savepoint, not a real
            // commit — afterCommit() correctly defers until the *outermost*
            // transaction commits either way, whereas dispatching right here
            // would fire even if that outer transaction later rolled back.
            DB::afterCommit(fn () => PaymentRecorded::dispatch($payment));

            return $payment;
        });
    }
}
