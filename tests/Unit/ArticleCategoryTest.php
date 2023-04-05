<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Models\Article;
use App\Models\ArticleCategory;
use App\Models\User;
use Laravel\Sanctum\Sanctum;
use Illuminate\Foundation\Testing\RefreshDatabase;

class ArticleCategoryTest extends TestCase
{
    use RefreshDatabase;
    protected $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->refreshDatabase();

        // mock log in user get token
        $this->user = User::factory()->create();
        Sanctum::actingAs($this->user,['*']);
    }

    /**
     * Test Get Article Category
     * /api/v1/article-categories
     */
    public function testGetPopularCategories()
    {
        // create article categories first
        $articleCategories = ArticleCategory::factory()->count(5)->create();

        // get article categories
        $response = $this->getJson('/api/v1/article_categories');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data'
            ]);

        $this->assertEquals(5, $response->json('meta.total'));
    }
}
