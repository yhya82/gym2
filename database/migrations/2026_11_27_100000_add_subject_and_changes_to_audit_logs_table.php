<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Gives each entry structure alongside its sentence:
     *  - subject_type/subject_id: the record the entry is about, so the
     *    Audit Log can link straight to it;
     *  - changes: {field: {old, new}} for updates, shown as a Before/After
     *    table instead of one long "a: x → y, b: …" line;
     *  - user_agent: which browser/device an action came from;
     *  - user_id nullable: entries written by the app itself (the nightly
     *    membership expiry) are attributed to "System" instead of being
     *    silently skipped.
     *
     * Adding columns is DDL, not an UPDATE of existing rows, so the
     * append-only triggers are unaffected. Existing entries simply have no
     * subject or changes.
     */
    public function up(): void
    {
        Schema::table('audit_logs', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable()->change();

            $table->string('subject_type', 100)->nullable()->after('module');
            $table->unsignedBigInteger('subject_id')->nullable()->after('subject_type');
            $table->json('changes')->nullable()->after('description');
            $table->string('user_agent', 255)->nullable()->after('ip_address');

            $table->index(['subject_type', 'subject_id']);
            $table->index(['user_id', 'created_at']);
            $table->index(['action', 'created_at']);
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::table('audit_logs', function (Blueprint $table) {
            $table->dropIndex(['subject_type', 'subject_id']);
            $table->dropIndex(['user_id', 'created_at']);
            $table->dropIndex(['action', 'created_at']);
            $table->dropIndex(['created_at']);

            $table->dropColumn(['subject_type', 'subject_id', 'changes', 'user_agent']);
        });
    }
};
