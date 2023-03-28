<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use App\Models\Interaction;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Interaction>
 */
class InteractionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition()
    {
        return [
            'interactable_id' => 1,
            'interactable_type' => Article::class,
            'user_id' => 1,
            'type' => Interaction::TYPE_LIKE,
            'status' => Interaction::STATUS_PUBLISHED,
        ];
    }
}
