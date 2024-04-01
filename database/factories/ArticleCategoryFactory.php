<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ArticleCategory>
 */
class ArticleCategoryFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition()
    {
        $name =  $this->faker->name;
        return [
            'name' => $name,
            'name_translation' => json_encode([
                'en' => $name,
                'zh' => $name,
            ]),
            'slug' => Str::slug($name),
            'cover_media_id' => null,
            'description' => $this->faker->text,
            'user_id' => 1,
        ];
    }
}
