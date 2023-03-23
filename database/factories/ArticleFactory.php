<?php

namespace Database\Factories;

use App\Models\Article;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Article>
 */
class ArticleFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition()
    {
        $title = fake()->sentence(1);
        $published_at = fake()->dateTimeBetween('-1 year', 'now');
        $status = 0; // default is draft
        if ($published_at < now()) {
            $status = fake()->randomElement([0, 1]); // can be draft or published 
        }

        return [
            'title' => $title,
            'slug' => Str::slug(fake()->asciify('******')),
            'excerpt' => fake()->sentence(10),
            'body' => fake()->paragraphs(3, true),
            'type' => fake()->randomElement(Article::TYPE),
            'status' => $status,
            'published_at' => $published_at,
            'user_id' => User::factory(),
        ];
    }

    public function published()
    {
        return $this->state(fn (array $attributes) => [
            'status' => 1,
            'published_at' => now()->subMinutes(15), // past date
        ]);
    }

    public function multimediaTyped()
    {
        return $this->state(fn (array $attributes) => [
            'type' => Article::TYPE[0],
        ]);
    }

    public function textTyped()
    {
        return $this->state(fn (array $attributes) => [
            'type' => Article::TYPE[1],
        ]);
    }

    public function videoTyped()
    {
        return $this->state(fn (array $attributes) => [
            'type' => Article::TYPE[2],
        ]);
    }
}
