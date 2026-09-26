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
        Schema::table('users', function (Blueprint $table) {
            // Nullable: existing users have none yet. The application layer
            // requires it going forward (StoreUserRequest/UpdateUserRequest),
            // but the column itself can't be NOT NULL without backfilling
            // every existing row first.
            $table->string('phone_number', 20)->nullable()->unique()->after('email');
        });

        // Same format backstop as members.phone_number — NULL values pass a
        // MySQL CHECK constraint automatically, so this doesn't conflict
        // with existing users who have no phone number yet.
        DB::statement('ALTER TABLE users ADD CONSTRAINT chk_users_phone_format CHECK (phone_number REGEXP \'^[+][1-9][0-9]{6,14}$\')');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('ALTER TABLE users DROP CONSTRAINT chk_users_phone_format');

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('phone_number');
        });
    }
};
