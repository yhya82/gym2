<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ApplicationSettingSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Singleton row: id is always 1, guaranteed by the chk_app_settings_singleton
        // CHECK constraint. updateOrInsert keeps this seeder idempotent on reseed.
        DB::table('application_settings')->updateOrInsert(
            ['id' => 1],
            [
                'application_name' => 'Gym Management System',
                'logo' => null,
                'location' => null,
                'email' => null,
                'phone' => null,
                'currency' => 'GMD',
                'timezone' => 'Africa/Banjul',
                'default_theme' => 'light',
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );
    }
}
