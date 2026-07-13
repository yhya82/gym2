<?php

namespace Database\Factories;

use App\Enums\MembershipStatus;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<\App\Models\Member>
 */
class MemberFactory extends Factory
{
    public function definition(): array
    {
        return [
            'full_name' => fake()->name(),
            // Gambian-format E.164 number matching chk_members_phone_format;
            // the leading 7 keeps every generated number valid per
            // PhoneNumberService's default GM region.
            'phone_number' => '+2207'.fake()->unique()->numerify('######'),
            'status' => MembershipStatus::Active,
            'created_by' => User::factory(),
        ];
    }
}
