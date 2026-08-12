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
     
{
    $this->call([
        ApplicationSettingSeeder::class,
        PlanSeeder::class,
    ]);

    $email = env('ADMIN_SEED_EMAIL', 'admin@gym.test');
    $password = env('ADMIN_SEED_PASSWORD');

    if (! $password) {
        if (app()->environment(['local', 'testing'])) {
            $password = 'password';
        } else {
            throw new \RuntimeException(
                'ADMIN_SEED_PASSWORD must be set before seeding outside local/testing.'
            );
        }
    }

    DB::table('users')->updateOrInsert(
        ['email' => $email],
        [
            'name' => 'Admin',
            'email_verified_at' => now(),
            'password' => Hash::make($password),
            'role' => 'admin',
            'created_at' => now(),
            'updated_at' => now(),
        ]
    );
}
    }
}
