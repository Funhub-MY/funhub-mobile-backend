<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Store>
 */
class StoreFactory extends Factory
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
            'business_phone_no' => fake()->randomNumber(9),
            'address' => fake()->address(),
            'address_postcode' => fake()->postcode(),
            'lang' => fake()->latitude,
            'long' => fake()->longitude,
            'is_hq' => fake()->boolean(),
            'state_id' => 1, // mock id only
            'country_id' => 1,
            'created_at' => now(),
            'updated_at' => now()
        ];
    }
}
