<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Replaces the phone_active generated-column approach (which let an
     * archived member's number be reused by a new member) with a plain
     * unique index on phone_number itself — a number is now unique forever,
     * archived or not.
     */
    public function up(): void
    {
        Schema::table('members', function (Blueprint $table) {
            $table->dropUnique('members_phone_active_unique');
            $table->dropColumn('phone_active');
            $table->unique('phone_number');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('members', function (Blueprint $table) {
            $table->dropUnique(['phone_number']);

            $table->string('phone_active', 20)->nullable()
                ->virtualAs('IF(`deleted_at` IS NULL, `phone_number`, NULL)');
            $table->unique('phone_active');
        });
    }
};
