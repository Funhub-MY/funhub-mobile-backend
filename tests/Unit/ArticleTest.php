<?php

namespace Tests\Unit;

use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

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
            factory(User::class)->create(),
            ['*']
        );

        // factory create articles
        factory(Article::class, 10)
            ->published()
            ->create();

        $response = $this->get('/api/v1/articles');

        $response->assertStatus(200)
        ->assertJsonStructure([
            'data',
        ]);
    }
}
