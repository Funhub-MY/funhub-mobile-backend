<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Models\Article;
use App\Models\User;
use Laravel\Sanctum\Sanctum;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;

class ArticleTest extends TestCase
{
    use RefreshDatabase;
    protected function setUp(): void
    {
        parent::setUp();
    }

    /**
     * Article get articles by logged in user
     * /api/v1/articles
     */
    public function testGetArticlesByLoggedInUser()
    {
        // mock log in user get token
        Sanctum::actingAs(
            User::factory()->create(),
            ['*']
        );

        // factory create articles
        Article::factory()
            ->count(10)
            ->published()
            ->create();

        $response = $this->getJson('/api/v1/articles');

        Log::info($response->getContent());

        $response->assertStatus(200)
        ->assertJsonStructure([
            'data',
        ]);
    }
}
