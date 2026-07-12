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
        Schema::create('application_settings', function (Blueprint $table) {
            $table->unsignedTinyInteger('id')->primary();
            $table->string('application_name');
            $table->string('logo')->nullable();
            $table->string('location')->nullable();
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->string('currency', 3);
            $table->string('timezone');
            $table->enum('default_theme', ['light', 'dark'])->default('light');
            $table->timestamps();
        });

        // Singleton table: id is always seeded as 1, never auto-generated, so this
        // CHECK is what actually guarantees a second row can never exist.
        DB::statement('ALTER TABLE application_settings ADD CONSTRAINT chk_app_settings_singleton CHECK (id = 1)');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('application_settings');
    }
};
