<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\CampaignQuestion>
 */
class CampaignQuestionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition()
    {
        return [
            'brand' => $this->faker->word,
            'question' => $this->faker->sentence,
            'answer_type' => $this->faker->randomElement(['text', 'radio', 'checkbox', 'select']),
        ];
    }
}
