<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->enum('type', ['member_created', 'membership_renewed', 'membership_expired']);
            $table->string('message', 500);
            $table->boolean('read_status')->default(false);
            // No updated_at: a notification is immutable once created, aside from
            // the read_status flag, and fan-out means one row per recipient.
            $table->timestamp('created_at')->useCurrent();

            // Matches the notification panel query exactly: unread-first, most recent.
            $table->index(['user_id', 'read_status', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('notifications');
    }
};
