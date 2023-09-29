<?php

namespace Database\Factories;

use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Product>
 */
class ProductFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition()
    {
        $unitPrice = $this->faker->randomFloat(2, 0, 100);
        return [
            'name' => $this->faker->name,
            'status' => Product::STATUS_PUBLISHED,
            'sku' => random_int(100000, 999999),
            'description' => $this->faker->text,
            'unit_price' => $unitPrice,
            // 20 percent off
            'discount_price' => $unitPrice * 0.8,
            'quantity' => 0,
            'unlimited_supply' => true
        ];
    }
}
