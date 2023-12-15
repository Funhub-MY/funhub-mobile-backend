<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Campaign>
 */
class CampaignFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition()
    {
        return [
            'title' => $this->faker->sentence(1),
            'description' => $this->faker->paragraph(1),
            'is_active' => $this->faker->boolean(),
            'remarks' => $this->faker->paragraph(1),
            'url' => $this->faker->url(),
        ];
    }
}
