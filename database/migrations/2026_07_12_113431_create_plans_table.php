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
        Schema::create('plans', function (Blueprint $table) {
            $table->id();
            $table->string('plan_name', 100);
            $table->unsignedSmallInteger('duration_days');
            $table->decimal('price', 10, 2);
            $table->softDeletes();
            $table->timestamps();

            $table->string('plan_name_active', 100)->nullable()
                ->virtualAs('IF(`deleted_at` IS NULL, `plan_name`, NULL)');
            $table->unique('plan_name_active');
        });

        DB::statement('ALTER TABLE plans ADD CONSTRAINT chk_plans_duration CHECK (duration_days > 0)');
        DB::statement('ALTER TABLE plans ADD CONSTRAINT chk_plans_price CHECK (price >= 0)');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('plans');
    }
};
