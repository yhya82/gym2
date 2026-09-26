<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Matches members.phone_number and users.phone_number: same length,
     * same format CHECK. Stays nullable — the gym's own contact number is
     * optional, unlike a member's or staff member's.
     */
    public function up(): void
    {
        Schema::table('application_settings', function (Blueprint $table) {
            $table->string('phone', 20)->nullable()->change();
        });

        DB::statement('ALTER TABLE application_settings ADD CONSTRAINT chk_app_settings_phone_format CHECK (phone REGEXP \'^[+][1-9][0-9]{6,14}$\')');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('ALTER TABLE application_settings DROP CONSTRAINT chk_app_settings_phone_format');

        Schema::table('application_settings', function (Blueprint $table) {
            $table->string('phone', 255)->nullable()->change();
        });
    }
};
