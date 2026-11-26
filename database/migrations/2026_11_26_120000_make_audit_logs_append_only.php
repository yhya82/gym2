<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Makes audit_logs append-only at the database level, the same way
     * trg_payments_before_insert enforces the overpayment rule: the app
     * never edits or deletes an entry, and now nothing else can either —
     * not a stray query, a tinker session, or a compromised account
     * trying to cover its tracks.
     *
     * Deletes are allowed only for entries past the 12-month retention
     * (AuditLog::RETENTION_MONTHS), which is what the scheduled model:prune
     * removes. created_at is stored in UTC (config/app.php), hence
     * UTC_TIMESTAMP() rather than NOW().
     */
    public function up(): void
    {
        DB::unprepared('
            CREATE TRIGGER trg_audit_logs_no_update
            BEFORE UPDATE ON audit_logs
            FOR EACH ROW
            BEGIN
                SIGNAL SQLSTATE \'45000\'
                SET MESSAGE_TEXT = \'Audit log entries cannot be changed\';
            END
        ');

        DB::unprepared('
            CREATE TRIGGER trg_audit_logs_no_early_delete
            BEFORE DELETE ON audit_logs
            FOR EACH ROW
            BEGIN
                IF OLD.created_at >= UTC_TIMESTAMP() - INTERVAL 12 MONTH THEN
                    SIGNAL SQLSTATE \'45000\'
                    SET MESSAGE_TEXT = \'Audit log entries cannot be deleted until they are 12 months old\';
                END IF;
            END
        ');
    }

    public function down(): void
    {
        DB::unprepared('DROP TRIGGER IF EXISTS trg_audit_logs_no_update');
        DB::unprepared('DROP TRIGGER IF EXISTS trg_audit_logs_no_early_delete');
    }
};
