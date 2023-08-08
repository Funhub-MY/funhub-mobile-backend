<?php

namespace Database\Factories;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\MerchantOffer>
 */
class MerchantOfferFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     * @throws \Exception
     */
    public function definition()
    {
        $now = Carbon::now();
        $random_number = random_int(2,99);
        return [
            'name' => fake()->name(),
            'status' => 1,
            'description' => fake()->paragraph(1),
            'unit_price' => fake()->randomFloat(2, 0, 100),
            'fiat_price' => fake()->randomFloat(2, 0, 100),
            'discounted_fiat_price' => fake()->randomFloat(2, 0, 100),
            'point_fiat_price' => fake()->randomFloat(2, 0, 100),
            'discounted_point_fiat_price' => fake()->randomFloat(2, 0, 100),
            'currency' => 'MYR',
            'available_at' => $now,
            'available_until' => $now->copy()->addDays($random_number),
            'quantity' => random_int(1,99),
            'sku' => 'ABC-123',
            'created_at' => now(),
            'updated_at' => now()
        ];
    }
}
