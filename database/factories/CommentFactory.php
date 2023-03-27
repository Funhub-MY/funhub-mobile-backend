<?php

namespace Database\Factories;

use App\Models\Article;
use App\Models\Comment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Comment>
 */
class CommentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition()
    {
        return [
            'commentable_id' => 1,
            'commentable_type' => Article::class,
            'user_id' => 1,
            'parent_id' => null,
            'body' => $this->faker->paragraph,
            'status' => Comment::STATUS_PUBLISHED,
        ];
    }
}
