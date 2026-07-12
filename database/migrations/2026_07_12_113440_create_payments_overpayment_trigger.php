<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Enforces that a payment can never push a subscription's amount_paid past
        // its plan_price. This can't be a CHECK constraint since it spans two
        // tables (payments + subscriptions) and an aggregate; the subscriptions-side
        // CHECK (balance = plan_price - amount_paid) is a row-level backstop, but
        // this trigger is the actual gate at the moment a payment is inserted.
        // The row lock (FOR UPDATE) on the subscription serializes concurrent
        // payments against the same subscription so two simultaneous inserts can't
        // both pass the check and jointly overshoot the limit.
        DB::unprepared('
            CREATE TRIGGER trg_payments_before_insert
            BEFORE INSERT ON payments
            FOR EACH ROW
            BEGIN
                DECLARE v_plan_price DECIMAL(10,2);
                DECLARE v_amount_paid DECIMAL(10,2);

                SELECT plan_price, amount_paid INTO v_plan_price, v_amount_paid
                FROM subscriptions
                WHERE id = NEW.subscription_id
                FOR UPDATE;

                IF v_amount_paid + NEW.amount > v_plan_price THEN
                    SIGNAL SQLSTATE \'45000\'
                    SET MESSAGE_TEXT = \'Payment exceeds remaining balance on subscription\';
                END IF;
            END
        ');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::unprepared('DROP TRIGGER IF EXISTS trg_payments_before_insert');
    }
};
