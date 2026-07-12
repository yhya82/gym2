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
        Schema::create('members', function (Blueprint $table) {
            $table->id();
            $table->string('full_name');
            $table->string('phone_number', 20);
            $table->enum('status', ['active', 'expired']);
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->softDeletes();
            $table->timestamps();

            $table->string('phone_active', 20)->nullable()
                ->virtualAs('IF(`deleted_at` IS NULL, `phone_number`, NULL)');
            $table->unique('phone_active');

            $table->index('full_name');
            $table->index(['deleted_at', 'status']);
        });

        // Format-only backstop; semantic validity (real country/area codes) is
        // enforced by libphonenumber at the application layer before insert.
        // The leading "+" is matched via a single-char class ([+]) rather than
        // an escaped "\+", since backslash is itself special inside a MySQL/
        // MariaDB string literal and would otherwise be stripped before the
        // regex engine ever sees it.
        DB::statement('ALTER TABLE members ADD CONSTRAINT chk_members_phone_format CHECK (phone_number REGEXP \'^[+][1-9][0-9]{6,14}$\')');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('members');
    }
};
