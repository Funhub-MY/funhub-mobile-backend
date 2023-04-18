<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Merchant>
 */
class MerchantFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition()
    {
        return [
            'name' => fake()->name(),
            'business_name' => fake()->company(),
            'business_phone_no' => fake()->randomNumber(9),
            'address' => fake()->address(),
            'address_postcode' => fake()->postcode(),
            'pic_name' => fake()->name(),
            'pic_phone_no' => fake()->randomNumber(9),
            'pic_email' => fake()->unique()->safeEmail(),
            'state_id' => 1, // mock id only
            'country_id' => 1,  // mock id only
            'created_at' => now(),
            'updated_at' => now()
        ];
    }
}
