<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class PlanSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Matches the example catalog in the spec (§6): Monthly/Weekly/Daily.
        // updateOrInsert on plan_name keeps this idempotent on reseed.
        $plans = [
            ['plan_name' => 'Monthly', 'duration_days' => 30, 'price' => 500.00],
            ['plan_name' => 'Weekly', 'duration_days' => 7, 'price' => 150.00],
            ['plan_name' => 'Daily', 'duration_days' => 1, 'price' => 30.00],
        ];

        foreach ($plans as $plan) {
            DB::table('plans')->updateOrInsert(
                ['plan_name' => $plan['plan_name']],
                [
                    'duration_days' => $plan['duration_days'],
                    'price' => $plan['price'],
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );
        }
    }
}
