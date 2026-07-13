<?php

namespace Database\Factories;

use App\Enums\MembershipStatus;
use App\Models\Member;
use App\Models\Plan;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<\App\Models\Subscription>
 */
class SubscriptionFactory extends Factory
{
    public function definition(): array
    {
        $price = fake()->randomFloat(2, 10, 200);

        return [
            'member_id' => Member::factory(),
            'plan_id' => Plan::factory(),
            'start_date' => now()->toDateString(),
            'expiry_date' => now()->addDays(30)->toDateString(),
            'plan_price' => $price,
            'amount_paid' => 0,
            'balance' => $price,
            'status' => MembershipStatus::Active,
        ];
    }
}
