<?php

namespace Database\Factories;

use App\Models\RatingCategory;
use Illuminate\Database\Eloquent\Factories\Factory;

class RatingCategoryFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = RatingCategory::class;

    /**
     * Define the model's default state.
     *
     * @return array
     */
    public function definition()
    {
        $name = $this->faker->word;
        return [
            'name' => $name,
            'name_translations' => json_encode( [
                'en' => $name,
                'zh' => $this->faker->word,
            ])
        ];
    }
}
