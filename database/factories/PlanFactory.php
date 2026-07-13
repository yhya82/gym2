<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<\App\Models\Plan>
 */
class PlanFactory extends Factory
{
    public function definition(): array
    {
        return [
            'plan_name' => fake()->unique()->words(2, true).' Plan',
            'duration_days' => fake()->randomElement([7, 30, 90]),
            'price' => fake()->randomFloat(2, 10, 200),
        ];
    }
}
