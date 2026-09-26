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
        // Guarded rather than unconditional: an environment whose members
        // table was seeded from a different migration history (or already
        // had this migration partially applied) may be missing the index
        // or column below — dropping something that isn't there would
        // otherwise fail the whole deploy instead of just being a no-op.
        if (Schema::hasIndex('members', 'members_phone_active_unique')) {
            Schema::table('members', function (Blueprint $table) {
                $table->dropUnique('members_phone_active_unique');
            });
        }

        if (Schema::hasColumn('members', 'phone_active')) {
            Schema::table('members', function (Blueprint $table) {
                $table->dropColumn('phone_active');
            });
        }

        if (! Schema::hasIndex('members', 'members_phone_number_unique')) {
            Schema::table('members', function (Blueprint $table) {
                $table->unique('phone_number');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasIndex('members', 'members_phone_number_unique')) {
            Schema::table('members', function (Blueprint $table) {
                $table->dropUnique(['phone_number']);
            });
        }

        if (! Schema::hasColumn('members', 'phone_active')) {
            Schema::table('members', function (Blueprint $table) {
                $table->string('phone_active', 20)->nullable()
                    ->virtualAs('IF(`deleted_at` IS NULL, `phone_number`, NULL)');
            });
        }

        if (! Schema::hasIndex('members', 'members_phone_active_unique')) {
            Schema::table('members', function (Blueprint $table) {
                $table->unique('phone_active');
            });
        }
    }
};
