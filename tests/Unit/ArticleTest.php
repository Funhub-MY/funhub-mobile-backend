<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Models\Article;
use App\Models\User;
use Laravel\Sanctum\Sanctum;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

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

    /**
     * Articles create article by logged in user without images
     * /api/v1/articles
     */
    public function testCreateArticleByLoggedInUserWihtoutImages()
    {
        $this->refreshDatabase();

        $user = User::factory()->create();
         // mock log in user get token
         Sanctum::actingAs(
            $user,
            ['*']
        );
        
        // create article category factory
        $categories = \App\Models\ArticleCategory::factory()
            ->count(2)
            ->create();


        $response = $this->postJson('/api/v1/articles', [
            'title' => 'Test Article',
            'body' => 'Test Article Body',
            'type' => 'multimedia',
            'published_at' => now(),
            'status' => 1,
            'published_at' => now()->toDateTimeString(),
            'tags' => ['#test', '#test2'],
            'categories' => $categories->pluck('id')->toArray(),
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'message',
                'article',
            ]);

        $this->assertDatabaseHas('articles', [
            'title' => 'Test Article',
            'body' => 'Test Article Body',
            'type' => 'multimedia',
            'status' => 1,
            'user_id' => $user->id,
        ]);

        // check article categories (two categories)
        $this->assertDatabaseHas('articles_article_categories', [
            'article_id' => 1,
            'article_category_id' => $categories->first()->id,
        ]);
        $this->assertDatabaseHas('articles_article_categories', [
            'article_id' => 1,
            'article_category_id' => $categories->last()->id,
        ]);

        // check article has tags attached (two tags)
        $this->assertDatabaseHas('articles_article_tags', [
            'article_id' => 1,
            'article_tag_id' => 1,
        ]);
        $this->assertDatabaseHas('articles_article_tags', [
            'article_id' => 1,
            'article_tag_id' => 2,
        ]);
    }

    /**
     * Articles gallery upload before article is created
     * /api/v1/articles/gallery
     */
    public function testGalleryUploadBeforeArticleIsCreated()
    {
        $this->refreshDatabase();

        $user = User::factory()->create();
         // mock log in user get token
         Sanctum::actingAs(
            $user,
            ['*']
        );

        $response = $this->json('POST', '/api/v1/articles/gallery', [
            'images' => UploadedFile::fake()->image('test.jpg')
        ]);

        $this->assertTrue($user->media->where('file_name', 'test.jpg')->count() > 0);
        
        $response->assertStatus(200)
            ->assertJsonStructure([
                'uploaded'
            ]);
    }

    /**
     * Articles create article by logged in user with images
     * /api/v1/articles
     */
    public function testCreateArticleByLoggedInUserWithImages()
    {

    }
}
