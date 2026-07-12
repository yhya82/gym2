<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            ApplicationSettingSeeder::class,
            PlanSeeder::class,
        ]);

        // Seeded directly via the query builder rather than the User model/factory,
        // since Eloquent models haven't been built yet (that's Phase 3).
        DB::table('users')->updateOrInsert(
            ['email' => 'admin@gym.test'],
            [
                'name' => 'Admin',
                'email_verified_at' => now(),
                'password' => Hash::make('password'),
                'role' => 'admin',
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );
    }
}
