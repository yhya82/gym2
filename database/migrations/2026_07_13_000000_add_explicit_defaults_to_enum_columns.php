<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * ENUM columns are exempt from STRICT_TRANS_TABLES' "no implicit
     * default" rule: omitting one on insert silently stores the first
     * enum value instead of erroring. users.role's first value is 'admin',
     * so any future bug or raw insert that forgets to set it would
     * silently create an Admin account. Explicit DEFAULTs make the
     * fallback intentional and least-privileged/safest, instead of
     * accidental and highest-privileged.
     */
    public function up(): void
    {
        DB::statement("ALTER TABLE users MODIFY role ENUM('admin', 'receptionist') NOT NULL DEFAULT 'receptionist'");
        DB::statement("ALTER TABLE members MODIFY status ENUM('active', 'expired') NOT NULL DEFAULT 'expired'");
        DB::statement("ALTER TABLE subscriptions MODIFY status ENUM('active', 'expired') NOT NULL DEFAULT 'expired'");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE users MODIFY role ENUM('admin', 'receptionist') NOT NULL");
        DB::statement("ALTER TABLE members MODIFY status ENUM('active', 'expired') NOT NULL");
        DB::statement("ALTER TABLE subscriptions MODIFY status ENUM('active', 'expired') NOT NULL");
    }
};
