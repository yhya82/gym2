<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Security Center policy, stored on the existing settings singleton so it
     * rides the same cached ApplicationSetting::current() read every page
     * already does. Defaults reproduce the app's behavior before this
     * migration (8-char minimum, no character classes, no history, the
     * .env SESSION_LIFETIME of 120) so nothing changes until an Admin edits
     * the policy.
     */
    public function up(): void
    {
        Schema::table('application_settings', function (Blueprint $table) {
            $table->unsignedTinyInteger('password_min_length')->default(8);
            $table->boolean('password_require_uppercase')->default(false);
            $table->boolean('password_require_lowercase')->default(false);
            $table->boolean('password_require_number')->default(false);
            $table->boolean('password_require_symbol')->default(false);
            $table->unsignedTinyInteger('lockout_attempts')->default(5);
            $table->unsignedSmallInteger('lockout_minutes')->default(10);
            $table->unsignedTinyInteger('password_history')->default(0);
            $table->unsignedSmallInteger('session_timeout_minutes')->default(120);
        });

        // Same bounds the Security Center form validates against — the DB is
        // the backstop if anything ever writes these columns another way.
        DB::statement('ALTER TABLE application_settings ADD CONSTRAINT chk_app_settings_password_min_length CHECK (password_min_length BETWEEN 6 AND 64)');
        DB::statement('ALTER TABLE application_settings ADD CONSTRAINT chk_app_settings_lockout_attempts CHECK (lockout_attempts BETWEEN 1 AND 20)');
        DB::statement('ALTER TABLE application_settings ADD CONSTRAINT chk_app_settings_lockout_minutes CHECK (lockout_minutes BETWEEN 1 AND 1440)');
        DB::statement('ALTER TABLE application_settings ADD CONSTRAINT chk_app_settings_password_history CHECK (password_history BETWEEN 0 AND 24)');
        DB::statement('ALTER TABLE application_settings ADD CONSTRAINT chk_app_settings_session_timeout CHECK (session_timeout_minutes BETWEEN 5 AND 1440)');

        // ApplicationSetting::current() is cached forever — a copy cached
        // before this migration would be missing every column above.
        Cache::forget('application_settings');
    }

    public function down(): void
    {
        foreach ([
            'chk_app_settings_password_min_length',
            'chk_app_settings_lockout_attempts',
            'chk_app_settings_lockout_minutes',
            'chk_app_settings_password_history',
            'chk_app_settings_session_timeout',
        ] as $constraint) {
            DB::statement("ALTER TABLE application_settings DROP CONSTRAINT {$constraint}");
        }

        Schema::table('application_settings', function (Blueprint $table) {
            $table->dropColumn([
                'password_min_length',
                'password_require_uppercase',
                'password_require_lowercase',
                'password_require_number',
                'password_require_symbol',
                'lockout_attempts',
                'lockout_minutes',
                'password_history',
                'session_timeout_minutes',
            ]);
        });

        Cache::forget('application_settings');
    }
};
