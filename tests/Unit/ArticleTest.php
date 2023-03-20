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
     * Article get articles for Home by logged in user
     * /api/v1/articles
     */
    public function testGetArticlesForHomeByLoggedInUser()
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

        $response->assertStatus(200)
        ->assertJsonStructure([
            'data',
        ]);
    }

    /**
     * Article get articles for Profile by logged in user
     * /api/v1/articles/my_articles
     *
     */
    public function testGetArticlesForProfileByLoggedInUser()
    {
        $this->refreshDatabase();

        $user = User::factory()->create();
        // mock log in user get token
        Sanctum::actingAs(
            $user,
            ['*']
        );

        // factory create articles creayed by this user
        Article::factory()
            ->count(3)
            ->published()
            ->create([
                'user_id' => $user->id,
            ]);

        $response = $this->getJson('/api/v1/articles/my_articles');
        $response->assertStatus(200)
            ->assertJsonStructure([
                'data',
            ]);

        $this->assertEquals(3, $response->json('meta.total'));
    }
    

    /**
     * Article get articles bookmarked by logged in user
     * /api/v1/articles/my_bookmarks
     */
    public function testGetArticlesBookmarkedByLoggedInUser()
    {
        $this->refreshDatabase();

        $user = User::factory()->create();
        // mock log in user get token
        Sanctum::actingAs(
            $user,
            ['*']
        );

        // create 5 random users used for creating 5 articles
        $users = User::factory()
            ->count(5)
            ->create();

        // factory create articles by random users
        $articles = Article::factory()
            ->count(5)
            ->published()
            ->create([
                'user_id' => $users->random()->id,
            ]);

        // bookmark each articles
        foreach ($articles as $article) {
            // bookmark articles by logged in user
            $response = $this->postJson('/api/v1/interactions', [
                'interactable' => 'article',
                'interaction_type' => 'bookmark',
                'id' => $article->id,
            ]);

            // assert json response
            $response->assertStatus(200)
                ->assertJsonStructure([
                    'interaction',
                ]);
        }

        // get bookmarks of user
        $response = $this->getJson('/api/v1/articles/my_bookmarks');
        $response->assertStatus(200)
            ->assertJsonStructure([
                'data',
            ]);

        $this->assertEquals(5, $response->json('meta.total'));
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
           [
                'article_id' => 1,
                'article_category_id' => 1,
           ],
            [
                'article_id' => 1,
                'article_category_id' => 2,
            ],
        ]);
        // check article has tags attached (two tags)
        $this->assertDatabaseHas('articles_article_tags', [
            [
                'article_id' => 1,
                'article_tag_id' => 1,
            ],
            [
                'article_id' => 1,
                'article_tag_id' => 2,
            ],
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
}
