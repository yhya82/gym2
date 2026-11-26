<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Every login attempt, successful or not — the Security Center's login
     * counters read from here. audit_logs can't hold an attempt against an
     * email that matches no account (its user_id is NOT NULL), which is
     * exactly the kind of attempt a security view most needs to count, so
     * user_id is nullable here.
     */
    public function up(): void
    {
        Schema::create('login_attempts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('email');
            $table->string('ip_address', 45)->nullable();
            $table->boolean('successful');
            // Append-only, like audit_logs.
            $table->timestamp('created_at')->useCurrent();

            $table->index(['successful', 'created_at']);
            $table->index('email');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('login_attempts');
    }
};
