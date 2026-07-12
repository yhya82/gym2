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
        Schema::create('subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('member_id')->constrained('members')->restrictOnDelete();
            $table->foreignId('plan_id')->constrained('plans')->restrictOnDelete();
            $table->date('start_date');
            $table->date('expiry_date');
            $table->decimal('plan_price', 10, 2);
            $table->decimal('amount_paid', 10, 2)->default(0);
            $table->decimal('balance', 10, 2);
            $table->enum('status', ['active', 'expired']);
            $table->timestamps();

            // Finds a member's current subscription without a join.
            $table->index(['member_id', 'expiry_date']);
            // Matches the expiry cron's scan predicate exactly.
            $table->index(['status', 'expiry_date']);
        });

        DB::statement('ALTER TABLE subscriptions ADD CONSTRAINT chk_subscriptions_dates CHECK (expiry_date > start_date)');
        DB::statement('ALTER TABLE subscriptions ADD CONSTRAINT chk_subscriptions_plan_price CHECK (plan_price >= 0)');
        DB::statement('ALTER TABLE subscriptions ADD CONSTRAINT chk_subscriptions_amount_paid CHECK (amount_paid >= 0)');
        DB::statement('ALTER TABLE subscriptions ADD CONSTRAINT chk_subscriptions_balance CHECK (balance >= 0)');
        // Guarantees the three columns can never drift out of arithmetic agreement;
        // this also transitively forbids amount_paid > plan_price (balance >= 0 + this).
        DB::statement('ALTER TABLE subscriptions ADD CONSTRAINT chk_subscriptions_balance_formula CHECK (balance = plan_price - amount_paid)');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('subscriptions');
    }
};
