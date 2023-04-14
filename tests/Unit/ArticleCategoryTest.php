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

    /**
     * Test Get Featured Article Category
     * /api/v1/article-categories?is_featured=1
     */
    public function testIsFeaturedCategory()
    {
        // create article categories first
        $articleCategories = ArticleCategory::factory()->count(5)->create([
            'is_featured' => true
        ]);
        
        // get featured article categories
        $response = $this->getJson('/api/v1/article_categories?is_featured=1');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data'
            ]);

        $this->assertEquals(5, $response->json('meta.total'));

        // check if is_featured in data each
        foreach ($response->json('data') as $category) {
            $this->assertEquals(true, $category['is_featured']);
        }
    }
}
