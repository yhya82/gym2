<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            // member_id is a denormalized shortcut (derivable via subscription_id ->
            // subscriptions.member_id) kept for query speed on payment search by member.
            $table->foreignId('member_id')->constrained('members')->restrictOnDelete();
            $table->foreignId('subscription_id')->constrained('subscriptions')->restrictOnDelete();
            $table->decimal('amount', 10, 2);
            $table->date('payment_date');
            $table->foreignId('received_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
            // No soft delete, no delete path at all: payment history is immutable.

            $table->index('payment_date');
        });

        DB::statement('ALTER TABLE payments ADD CONSTRAINT chk_payments_amount CHECK (amount > 0)');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
