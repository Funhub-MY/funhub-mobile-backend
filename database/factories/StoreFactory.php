<?php

namespace Database\Factories;

use App\Models\Country;
use App\Models\Merchant;
use App\Models\State;
use App\Models\User;
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
            'name' => fake()->company(),
            'manager_name' => fake()->name(),
            'business_phone_no' => fake()->phoneNumber(),
            'business_hours' => json_encode([
                '1' => ['open' => '09:00', 'close' => '18:00'],
                '2' => ['open' => '09:00', 'close' => '18:00'],
                '3' => ['open' => '09:00', 'close' => '18:00'],
                '4' => ['open' => '09:00', 'close' => '18:00'],
                '5' => ['open' => '09:00', 'close' => '18:00'],
                '6' => ['open' => '09:00', 'close' => '18:00'],
                '7' => ['open' => '09:00', 'close' => '18:00'],
            ]),
            'address' => fake()->address(),
            'address_postcode' => fake()->postcode(),
            'lang' => fake()->latitude(),
            'long' => fake()->longitude(),
            'is_hq' => fake()->boolean(),
            'user_id' => User::factory(),
            'state_id' => 1,
            'country_id' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }
}
