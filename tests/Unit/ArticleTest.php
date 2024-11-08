<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Models\Article;
use App\Models\ArticleCategory;
use App\Models\ArticleFeedWhitelistUser;
use App\Models\ArticleTag;
use App\Models\Country;
use App\Models\Location;
use App\Models\Setting;
use App\Models\State;
use App\Models\User;
use App\Models\View;
use Database\Seeders\CountriesTableSeeder;
use Database\Seeders\StatesTableSeeder;
use Laravel\Sanctum\Sanctum;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;

class ArticleTest extends TestCase
{
    use RefreshDatabase;
    protected $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->refreshDatabase();

        // mock log in user get token
        $this->user = User::factory()->create();
        Sanctum::actingAs($this->user, ['*']);
    }

    /**
     * Article get articles for Home by logged in user
     * /api/v1/articles
     */
    public function testGetArticlesForHomeByLoggedInUser()
    {
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

        $this->assertEquals(10, $response->json('meta.total'));
    }

    /**
     * Article get articles for Home filtered by category ids by logged in user
     * /api/v1/articles?category_ids=1,2,3
     */
    public function testGetArticlesForHomeFilteredByCategoryIdsByLoggedInUser()
    {
        $categories = ArticleCategory::factory()->count(2)->create();

        // factory create 10 articles
        $articles = Article::factory()
            ->count(10)
            ->published()
            ->create();

        // attach these two categories to 5 articles only
        collect($articles->take(5))->each(function ($article) use ($categories) {
            $article->categories()->attach($categories->pluck('id')->toArray());
        });

        $response = $this->getJson('/api/v1/articles?category_ids=' . $categories->pluck('id')->implode(','));

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data',
            ]);

        // ensure only 5 articles got taken
        $this->assertEquals(5, $response->json('meta.total'));

        // check 5 articles have correct article categories ids
        collect($response->json('data'))->each(function ($article) use ($categories) {
            // get article categories ids from $article['categories']
            $article_categories_id = collect($article['categories'])->pluck('id')->toArray();
            $this->assertEquals($categories->pluck('id')->toArray(), $article_categories_id);
        });
    }

    /**
     * Article get articles for Home filtered by tag ids by logged in user
     * /api/v1/articles?tag_ids=1,2,3
     */
    public function testGetArticlesForHomeFilteredByTagIdsByLoggedInUser()
    {
        $tags = ArticleTag::factory()->count(2)->create();

        // factory create 10 articles
        $articles = Article::factory()
            ->count(10)
            ->published()
            ->create();

        // attach these two tags to 5 articles only
        collect($articles->take(5))->each(function ($article) use ($tags) {
            $article->tags()->attach($tags->pluck('id')->toArray());
        });

        $response = $this->getJson('/api/v1/articles?tag_ids=' . $tags->pluck('id')->implode(','));

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data',
            ]);

        // ensure only 5 articles got taken
        $this->assertEquals(5, $response->json('meta.total'));

        // check 5 articles have correct article tags ids
        collect($response->json('data'))->each(function ($article) use ($tags) {
            // get article tags ids from $article['tags']
            $article_tag_id = collect($article['tags'])->pluck('id')->toArray();
            $this->assertEquals($tags->pluck('id')->toArray(), $article_tag_id);
        });
    }

    /**
     * Article get one article via show route
     * /api/v1/articles/{id}
     */
    public function testGetOneArticleByShowRouteByLoggedInUser()
    {
        $articles = Article::factory()->count(3)
            ->published()
            ->create();

        // get second article
        $secondArticle = $articles->get(2);

        // get second article
        $response = $this->getJson('/api/v1/articles/' . $secondArticle->id);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'article',
            ]);

        // cheeck article.id json is 2
        $this->assertEquals($secondArticle->id, $response->json('article.id'));
    }

    /**
     * Article get articles for Home by logged in user when user is following
     * /api/v1/articles
     */
    public function testGetArticlesForHomeByLoggedInUserWhenUserIsNotFollowing()
    {
        // factory create articles
        Article::factory()
            ->count(10)
            ->published()
            ->create();

        $response = $this->getJson('/api/v1/articles?following_only=1');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data',
            ]);

        // count is 0
        $this->assertEquals(0, $response->json('meta.total'));
    }

    /**
     * Article get articles for Profile by logged in user
     * /api/v1/articles/my_articles
     *
     */
    public function testGetArticlesForProfileByLoggedInUser()
    {
        // factory create articles creayed by this user
        Article::factory()
            ->count(3)
            ->published()
            ->create([
                'user_id' => $this->user->id,
            ]);

        $response = $this->getJson('/api/v1/articles/my_articles');
        $response->assertStatus(200)
            ->assertJsonStructure([
                'data',
            ]);

        $this->assertEquals(3, $response->json('meta.total'));
    }


    /**
     * Get Articles by users whose logged in user is following
     * /api/v1/articles
     */
    public function testGetArticlesByUserWhoseLoggedInUserIsFollowing()
    {
        // create new user
        $friend = User::factory()->create();

        // factory create articles creayed by this user
        Article::factory()
            ->count(5)
            ->published()
            ->create([
                'user_id' => $friend->id,
            ]);

        // logged in user follow this friend
        $response = $this->postJson('/api/v1/user/follow', [
            'user_id' => $friend->id,
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'message'
            ]);

        // get articles by my followings
        $response = $this->getJson('/api/v1/articles?following_only=1');
        $response->assertStatus(200)
            ->assertJsonStructure([
                'data',
            ]);

        // count is 5
        $this->assertEquals(5, $response->json('meta.total'));
    }


    /**
     * Article get articles bookmarked by logged in user
     * /api/v1/articles/my_bookmarks
     */
    public function testGetArticlesBookmarkedByLoggedInUser()
    {
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
                'type' => 'bookmark',
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
     * Article get articles by user id
     * /api/v1/my_articles
     */
    public function testGetMyArticlesByUserId()
    {
        // create a new user
        $user = User::factory()->create();

        // create ten articles by a user
        $articles = Article::factory()
            ->count(10)
            ->published()
            ->create([
                'user_id' => $user->id,
            ]);

        // get my_articles by user id
        $response = $this->getJson('/api/v1/articles/my_articles?user_id=' . $user->id);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data',
            ]);

        // assert is 10 articles
        $this->assertEquals(10, $response->json('meta.total'));
    }

    public function testGetMyBookmarkedArticlesByUserId()
    {
        // create a new user
        $user = User::factory()->create();

        // create ten articles by logged in user so $user can bookmark
        $articles = Article::factory()
            ->count(10)
            ->published()
            ->create([
                'user_id' => $this->user->id,
            ]);

        // bookmark each articles
        foreach ($articles as $article) {
            // bookmark articles by logged in user
            $response = $this->postJson('/api/v1/interactions', [
                'interactable' => 'article',
                'type' => 'bookmark',
                'id' => $article->id,
            ]);

            // assert json response
            $response->assertStatus(200)
                ->assertJsonStructure([
                    'interaction',
                ]);
        }
    }

    /**
     * Articles create article by logged in user without images
     * /api/v1/articles
     */
    public function testCreateArticleByLoggedInUserWihtoutImages()
    {
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
        $article_id = $response->json('article.id');

        $this->assertDatabaseHas('articles', [
            'title' => 'Test Article',
            'body' => 'Test Article Body',
            'type' => 'multimedia',
            'status' => 1,
            'user_id' => $this->user->id,
        ]);

        // check article categories (two categories)
        $this->assertDatabaseHas('articles_article_categories', [
            'article_id' => $article_id,
            'article_category_id' => $categories->first()->id,
        ]);
        $this->assertDatabaseHas('articles_article_categories', [
            'article_id' => $article_id,
            'article_category_id' => $categories->last()->id,
        ]);
        // check article has tags attached (two tags)
        $this->assertDatabaseHas('articles_article_tags', [
            'article_id' => $article_id,
            'article_tag_id' => ArticleTag::where('name', '#test')->first()->id,
        ]);
        $this->assertDatabaseHas('articles_article_tags', [
            'article_id' => $article_id,
            'article_tag_id' => ArticleTag::where('name', '#test2')->first()->id,
        ]);
    }

    /**
     * Articles create article with chinese title by logged in user
     * /api/v1/articles
     */
    public function testCreateArticleWithChineseCharactersAsTitle()
    {
        $response = $this->postJson('/api/v1/articles', [
            'title' => '測試文章',
            'body' => 'Test Article Body',
            'type' => 'multimedia',
            'published_at' => now(),
            'status' => 1,
            'published_at' => now()->toDateTimeString(),
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'message',
                'article',
            ]);

        $this->assertDatabaseHas('articles', [
            'title' => '測試文章',
            'body' => 'Test Article Body',
            'type' => 'multimedia',
            'status' => 1,
            'user_id' => $this->user->id,
        ]);
    }

    /**
     * Articles gallery upload before article is created
     * /api/v1/articles/gallery
     */
    public function testGalleryUploadBeforeArticleIsCreated()
    {
        $response = $this->json('POST', '/api/v1/articles/gallery', [
            'images' => UploadedFile::fake()->image('test.jpg')
        ]);

        $this->assertTrue($this->user->media->where('file_name', 'test.jpg')->count() > 0);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'uploaded'
            ]);
    }

    /**
     * Articles gallery upload with is_cover true before article is created
     * /api/v1/articles/gallery
     */
    public function testGalleryUploadIsCoverTrueBeforeArticleIsCreated()
    {
        $response = $this->json('POST', '/api/v1/articles/gallery', [
            'images' => UploadedFile::fake()->image('test.jpg'),
            'is_cover' => 1
        ]);

        $this->assertTrue($this->user->media->where('file_name', 'test.jpg')->first()->custom_properties['is_cover'] == 1);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'uploaded'
            ]);
    }

    /**
     * Create articles with images, tags and categories
     * /api/v1/articles
     */
    public function testCreateArticleByLoggedInUser()
    {
        // upload images first
        $response = $this->json('POST', '/api/v1/articles/gallery', [
            'images' => UploadedFile::fake()->image('test.jpg')
        ]);
        // create article category factory
        $categories = \App\Models\ArticleCategory::factory()
            ->count(2)
            ->create();

        // get ids array out of response json uploaded
        $image_ids = array_column($response->json('uploaded'), 'id');

        $response = $this->postJson('/api/v1/articles', [
            'title' => 'Test Article with Images',
            'body' => 'Test Article Body',
            'type' => 'multimedia',
            'published_at' => now(),
            'status' => 1,
            'published_at' => now()->toDateTimeString(),
            'tags' => ['#test', '#test2'],
            'categories' => $categories->pluck('id')->toArray(),
            'images' => $image_ids,
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'message',
                'article',
            ]);

        $article_id = $response->json('article.id');

        $this->assertDatabaseHas('articles', [
            'title' => 'Test Article with Images',
            'body' => 'Test Article Body',
            'type' => 'multimedia',
            'status' => 1,
            'user_id' => $this->user->id,
        ]);

        // check images are linked for this article
        $this->assertDatabaseHas('media', [
            'model_type' => 'App\Models\Article',
            'file_name' => 'test.jpg',
        ]);
        // check article categories (two categories)
        $this->assertDatabaseHas('articles_article_categories', [
            'article_id' => $article_id,
            'article_category_id' => $categories->first()->id,
        ]);
        $this->assertDatabaseHas('articles_article_categories', [
            'article_id' => $article_id,
            'article_category_id' => $categories->last()->id,
        ]);
        // check article has tags attached (two tags)
        $this->assertDatabaseHas('articles_article_tags', [
            'article_id' => $article_id,
            'article_tag_id' => ArticleTag::where('name', '#test')->first()->id,
        ]);
        $this->assertDatabaseHas('articles_article_tags', [
            'article_id' => $article_id,
            'article_tag_id' => ArticleTag::where('name', '#test2')->first()->id,
        ]);
    }

    /**
     * Update Articles Change Body and Status
     * /api/v1/articles/{article}
     */
    public function testUpdateArticleBodyAndStatusByLoggedInUser()
    {
        // create one article by this user using factory
        $article = Article::factory()->create([
            'title' => 'Test Article',
            'body' => 'Test Article Body',
            'status' => 1,
            'user_id' => $this->user->id,
        ]);

        $response = $this->putJson('/api/v1/articles/' . $article->id, [
            'body' => 'Test Article Body Updated',
            'status' => 0, // unpublished
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'message'
            ]);

        // assert body and status is updated
        $this->assertDatabaseHas('articles', [
            'id' => $article->id,
            'user_id' => $this->user->id,
            'body' => 'Test Article Body Updated',
            'status' => 0,
        ]);
    }

    /**
     * Article liked by logged in user
     * /api/v1/articles/{article}
     */
    public function testArticleLikeByLoggedInUser()
    {
        // create one article by this user using factory
        $article = Article::factory()->create([
            'title' => 'Test Article',
            'body' => 'Test Article Body',
            'status' => 1,
            'user_id' => $this->user->id,
        ]);

        $response = $this->postJson('/api/v1/interactions', [
            'id' => $article->id,
            'interactable' => 'article',
            'type' => 'like',
        ]);

        $response->assertStatus(200);

        // get article
        $response = $this->getJson('/api/v1/articles/' . $article->id);
        // check articles has interactions json
        $response->assertJsonStructure([
            'article' => [
                'interactions'
            ]
        ]);

        // check json article.interactions[0] has article_id
        $response->assertJson([
            'article' => [
                'interactions' => [
                    [
                        'user' => [
                            'id' => $this->user->id,
                        ],
                        'type' => 1
                    ]
                ]
            ]
        ]);
    }

    /**
     * Article liked by logged in user
     * /api/v1/articles
     */
    public function testGetArticleHomeLikedByLoggedInUser()
    {
        // create another user
        $otherUser = User::factory()->create();
        // create 10 articles
        $articles = Article::factory()->count(10)->published()
            ->create([
                'user_id' => $otherUser->id,
            ]);

        // log in user like all 10 articles
        foreach ($articles as $article) {
            $response = $this->postJson('/api/v1/interactions', [
                'id' => $article->id,
                'interactable' => Article::class,
                'type' => 'like',
            ]);

            // assert each like is 200 response
            $response->assertStatus(200);
        }

        // get articles home
        $response = $this->getJson('/api/v1/articles');

        // assert response is 200 and has data json structure
        $response->assertStatus(200)
            ->assertJsonStructure([
                'data'
            ]);

        // check each article has interactions and is liked by logged in user
        foreach ($response->json('data') as $article) {
            $this->assertArrayHasKey('interactions', $article);

            // assert article['interactions'] has user_id == logged in user id and type is 1
            $this->assertEquals($article['interactions'][0]['user']['id'], $this->user->id);
            $this->assertEquals($article['interactions'][0]['type'], 1);
        }
    }

    /**
     * Article bookmarked by logged in user
     * /api/v1/articles/{article}
     */
    public function testArticleBookmarkedByLoggedInUser()
    {
        // create one article by this user using factory
        $article = Article::factory()->create([
            'title' => 'Test Article',
            'body' => 'Test Article Body',
            'status' => 1,
            'user_id' => $this->user->id,
        ]);

        $response = $this->postJson('/api/v1/interactions', [
            'id' => $article->id,
            'interactable' => Article::class,
            'type' => 'bookmark',
        ]);

        $response->assertStatus(200);

        // get article
        $response = $this->getJson('/api/v1/articles/' . $article->id);
        // check articles has interactions json
        $response->assertJsonStructure([
            'article' => [
                'interactions'
            ]
        ]);

        // check response has user_bookmarked
        $response->assertJson([
            'article' => [
                'user_bookmarked' => true
            ]
        ]);

        // get articles bookmarked by this user
        $response = $this->getJson('/api/v1/articles/my_bookmarks');
        $response->assertStatus(200)
            ->assertJsonStructure([
                'data'
            ]);
        // meta.total should be 1
        $this->assertEquals(1, $response->json('meta.total'));
    }

    /**
     * Update Articles Change Categories
     * /api/v1/articles/{article}
     */
    public function testUpdateArticleChangeCategoriesByLoggedInUser()
    {
        $this->refreshDatabase();

        $user = User::factory()->create();
        Sanctum::actingAs($user, ['*']);

        // create one article by this user using factory
        $article = Article::factory()->create([
            'title' => 'Test Article',
            'body' => 'Test Article Body',
            'status' => 1,
            'user_id' => $user->id,
        ]);

        // create category (original)
        $category = \App\Models\ArticleCategory::factory()->count(3)->create();

        // attach created article with category
        $article->find($article->id)->categories()->attach($category->pluck('id')->toArray());

        // create 3 more different categories
        $categories_new = \App\Models\ArticleCategory::factory()->count(3)->create();

        $response = $this->putJson('/api/v1/articles/' . $article->id, [
            'body' => 'Test Article Body',
            'status' => 1, // unpublished
            'categories' => $categories_new->pluck('id')->toArray(),
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'message'
            ]);

        // assert body and status is updated
        $this->assertDatabaseHas('articles', [
            'id' => $article->id,
            'user_id' => $user->id,
            'body' => 'Test Article Body',
            'status' => 1,
        ]);

        $article = Article::find($article->id);

        // assert article has categories (new)
        $this->assertTrue(
            $article->categories->pluck('id')->toArray() == $categories_new->pluck('id')->toArray()
        );
    }

    /**
     * Get article by article_ids
     * /api/v1/articles?article_ids=1,2,3
     */
    public function testGetArticleByArticleIds()
    {
        // create one article by this user using factory
        $articles = Article::factory()->count(20)
            ->published()
            ->create();

        // get article
        $response = $this->getJson('/api/v1/articles?article_ids=' . implode(',', $articles->pluck('id')->toArray()));

        $response->assertJsonStructure([
            'data'
        ]);

        // check there's 20 items in meta.total
        $this->assertEquals(20, $response->json('meta.total'));
    }

    /**
     * Test View Article and get view count
     * /api/v1/articles/{article}
     */
    public function testViewArticle()
    {
        // create ten users
        $users = User::factory()->count(10)->create();

        // create a new article
        $article = Article::factory()->create([
            'user_id' => $this->user->id,
        ]);

        // each user view article
        foreach ($users as $user) {
            $this->actingAs($user);
            $response = $this->postJson('/api/v1/views', [
                'viewable_type' => 'article',
                'viewable_id' => $article->id,
            ]);

            $response->assertStatus(200)
                ->assertJsonStructure([
                    'message'
                ]);
        }

        // switch back to $this->user
        $this->actingAs($this->user);

        // article refresh
        $article->refresh();

        // check article view count
        $this->assertEquals(count($users), $article->views->count());
    }

    /**
     * Test Create Article with Location Tagged
     * /api/v1/articles
     */
    public function testCreateArticleWithLocationTagged()
    {
        // ensure countries and states are seeded first
        $this->seed(CountriesTableSeeder::class);
        $this->seed(StatesTableSeeder::class);

        // upload images first
        $response = $this->json('POST', '/api/v1/articles/gallery', [
            'images' => UploadedFile::fake()->image('test.jpg')
        ]);
        // create article category factory
        $categories = \App\Models\ArticleCategory::factory()
            ->count(2)
            ->create();

        // get ids array out of response json uploaded
        $image_ids = array_column($response->json('uploaded'), 'id');

        $response = $this->postJson('/api/v1/articles', [
            'title' => 'Test Article with Images',
            'body' => 'Test Article Body',
            'type' => 'multimedia',
            'published_at' => now(),
            'status' => 1,
            'published_at' => now()->toDateTimeString(),
            'tags' => ['#test', '#test2'],
            'categories' => $categories->pluck('id')->toArray(),
            'images' => $image_ids,
            'location' => [
                'name' => 'Test Location',
                'address' => 'Test Address',
                'lat' => 1.234,
                'lng' => 1.234,
                'address_2' => 'Test Address 2',
                'city' => 'Test City',
                'state' => 'Selangor',
                'postcode' => '123456',
                'rating' => 4
            ]
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'message',
                'article',
            ]);

        $article_id = $response->json('article.id');

        $this->assertDatabaseHas('articles', [
            'title' => 'Test Article with Images',
            'body' => 'Test Article Body',
            'type' => 'multimedia',
            'status' => 1,
            'user_id' => $this->user->id,
        ]);

        // get article by id
        $response = $this->getJson('/api/v1/articles/' . $article_id);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'article' => [
                    'location',
                ]
            ]);
        // check location data is correct
        $this->assertEquals('Test Location', $response->json('article.location.name'));

        // check if /api/articles/cities have one city
        $response = $this->getJson('/api/v1/article_cities');
        $response->assertStatus(200)
            ->assertJsonStructure([
                'cities',
            ]);

        $this->assertCount(1, $response->json('cities'));

        // assert city is Test City
        $this->assertEquals('Test City', $response->json('cities.0'));
    }

    /**
     * Test Delete Article with Location Tagged
     */
    public function testDeleteArticleWithLocationRating()
    {
        // ensure countries and states are seeded first
        $this->seed(CountriesTableSeeder::class);
        $this->seed(StatesTableSeeder::class);

        // upload images first
        $response = $this->json('POST', '/api/v1/articles/gallery', [
            'images' => UploadedFile::fake()->image('test.jpg')
        ]);
        // create article category factory
        $categories = \App\Models\ArticleCategory::factory()
            ->count(2)
            ->create();

        // get ids array out of response json uploaded
        $image_ids = array_column($response->json('uploaded'), 'id');

        $response = $this->postJson('/api/v1/articles', [
            'title' => 'Test Article with Images',
            'body' => 'Test Article Body',
            'type' => 'multimedia',
            'published_at' => now(),
            'status' => 1,
            'published_at' => now()->toDateTimeString(),
            'tags' => ['#test', '#test2'],
            'categories' => $categories->pluck('id')->toArray(),
            'images' => $image_ids,
            'location' => [
                'name' => 'Test Location',
                'address' => 'Test Address',
                'lat' => 1.234,
                'lng' => 1.234,
                'address_2' => 'Test Address 2',
                'city' => 'Test City',
                'state' => 'Selangor',
                'postcode' => '123456',
                'rating' => 4
            ]
        ]);
        $response->assertStatus(200)
            ->assertJsonStructure([
                'message',
                'article',
            ]);

        $article_id = $response->json('article.id');

        // delete article
        $response = $this->deleteJson('/api/v1/articles/' . $article_id);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'message',
            ]);

        // check location still exists in system
        $this->assertDatabaseHas('locations', [
            'name' => 'Test Location',
            'address' => 'Test Address',
            'lat' => 1.234,
            'lng' => 1.234,
            'address_2' => 'Test Address 2',
            'zip_code' => '123456',
            'average_ratings' => null,
        ]);
    }

    /**
     * Test Create & Update Article with followers tagged
     * /api/v1/articles
     */
    public function testCreateUpdateArticleWithFollowersTagged()
    {
        // upload images first
        $response = $this->json('POST', '/api/v1/articles/gallery', [
            'images' => UploadedFile::fake()->image('test.jpg')
        ]);
        // create article category factory
        $categories = \App\Models\ArticleCategory::factory()
            ->count(2)
            ->create();

        // get ids array out of response json uploaded
        $image_ids = array_column($response->json('uploaded'), 'id');

        // create ten users who follow $this->user
        $users = User::factory()->count(10)->create();
        foreach ($users as $user) {
            $this->actingAs($user); // act as follower
            $response = $this->postJson('/api/v1/user/follow', [
                'user_id' => $this->user->id, // follow global user
            ]);
            $response->assertStatus(200)
                ->assertJsonStructure([
                    'message'
                ]);
        }

        // switch back to $this->user
        $this->actingAs($this->user);

        $response = $this->getJson('/api/v1/user/followers');

        // assert should be 10 followers
        $this->assertCount(10, $response->json()['data']);

        // create article with users tagged
        $response = $this->postJson('/api/v1/articles', [
            'title' => 'Test Article with Images',
            'body' => 'Test Article Body',
            'type' => 'multimedia',
            'published_at' => now(),
            'status' => 1,
            'published_at' => now()->toDateTimeString(),
            'tags' => ['#test', '#test2'],
            'categories' => $categories->pluck('id')->toArray(),
            'images' => $image_ids,
            'tagged_user_ids' => $users->pluck('id')->toArray(),
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'message',
                'article'
            ]);

        // get article by id
        $response = $this->getJson('/api/v1/articles/tagged_users?article_id=' . $response->json('article.id'));

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data'
            ]);

        // loop each tagged_users check match with $users
        foreach ($response->json('data') as $tagged_user) {
            $this->assertContains($tagged_user['id'], $users->pluck('id')->toArray());
        }

        // edit tagged users id
        // create new sets of users
        $users = User::factory()->count(2)->create();
        // ensure users is followers of $this->user
        foreach ($users as $user) {
            $this->actingAs($user); // act as follower
            $response = $this->postJson('/api/v1/user/follow', [
                'user_id' => $this->user->id, // follow global user
            ]);
            $response->assertStatus(200)
                ->assertJsonStructure([
                    'message'
                ]);
        }

        // acting back to main logged in user
        $this->actingAs($this->user); // act as follower

        $response = $this->postJson('/api/v1/articles/' . $response->json('article.id'), [
            'title' => 'Test Article with Images',
            'body' => 'Test Article Body',
            'type' => 'multimedia',
            'published_at' => now(),
            'status' => 1,
            'published_at' => now()->toDateTimeString(),
            'tags' => ['#test', '#test2'],
            'categories' => $categories->pluck('id')->toArray(),
            'images' => $image_ids,
            'tagged_user_ids' => $users->pluck('id')->toArray(),
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'article'
            ]);

        // check if article has users tagged
        $response = $this->getJson('/api/v1/articles/tagged_users?article_id=' . $response->json('article.id'));
        $response->assertStatus(200)
            ->assertJsonStructure([
                'data'
            ]);

        // loop each tagged_users check match with $users
        foreach ($response->json('data') as $tagged_user) {
            $this->assertContains($tagged_user['id'], $users->pluck('id')->toArray());
        }
    }

    /**
     * Get articles by city
     */
    public function testGetArticlesByCity()
    {
        // ensure countries and states are seeded first
        $this->seed(CountriesTableSeeder::class);
        $this->seed(StatesTableSeeder::class);
        // create 1 user
        $otherUser = User::factory()->create();

        // create 10 articles
        $articles = Article::factory()->count(10)->published()
            ->create([
                'user_id' => $otherUser,
            ]);

        // 3 cities of Klang valley
        $cities = ['Kuala Lumpur', 'Petaling Jaya', 'Shah Alam'];

        $articles[0]->location()->create([
            'name' => 'Test Location',
            'address' => 'Test Address',
            'lat' => 1.234,
            'lng' => 1.234,
            'address_2' => 'Test Address 2',
            'city' => $cities[0],
            'state_id' => State::where('name', 'Selangor')->first()->id,
            'country_id' => Country::where('name', 'Malaysia')->first()->id,
            'zip_code' => '123456'
        ]);

        // attach remainder two location to each article
        foreach ($articles as $article) {
            $article->location()->create([
                'name' => 'Test Location',
                'address' => 'Test Address',
                'lat' => 1.234,
                'lng' => 1.234,
                'address_2' => 'Test Address 2',
                // randomize cities
                'city' => $cities[rand(1, 2)],
                'state_id' => State::where('name', 'Selangor')->first()->id,
                'country_id' => Country::where('name', 'Malaysia')->first()->id,
                'zip_code' => '123456'
            ]);
        }

        // get articles by city
        $response = $this->getJson('/api/v1/articles?city=' . $cities[0]);
        // assert data is 1
        $this->assertCount(1, $response->json('data'));

        // check data first article id is the $articles[0]
        $this->assertEquals($articles[0]->id, $response->json('data.0.id'));
    }

    /**
     * Get articles within my lat, lng
     */
    public function testGetArticlesWithintMyLatLng()
    {
        // ensure countries and states are seeded first
        $this->seed(CountriesTableSeeder::class);
        $this->seed(StatesTableSeeder::class);

        $otherUser = User::factory()->create();

        // create 10 articles
        $articles = Article::factory()->count(10)->published()
            ->create([
                'user_id' => $otherUser,
            ]);

        // 3 cities of Klang valley
        $cities = ['Kuala Lumpur', 'Petaling Jaya', 'Shah Alam'];
        foreach ($articles as $article) {
            $article->location()->create([
                'name' => 'Test Location',
                'address' => 'Test Address',
                'lat' => 3.0254065,
                'lng' => 101.5814762,
                'address_2' => 'Test Address 2',
                'city' => $cities[0],
                'state_id' => State::where('name', 'Selangor')->first()->id,
                'country_id' => Country::where('name', 'Malaysia')->first()->id,
                'zip_code' => '123456'
            ]);
        }

        // my location: 3.013814, 101.622510
        $response = $this->getJson('/api/v1/articles?lat=3.0254065&lng=101.5814762');

        // assert data is 10 as its nearby
        $this->assertCount(10, $response->json('data'));
    }

    /**
     * To ensure consistency in resources returned via different endpoints after updating an article
     */
    public function testUpdateConsistency()
    {
        // ensure countries and states are seeded first
        $this->seed(CountriesTableSeeder::class);
        $this->seed(StatesTableSeeder::class);

        // 1. Factory create 5 articles by 5 different users
        $users = User::factory()->count(5)->create();

        $articles = collect();
        foreach ($users as $user) {
            $article = Article::factory()->published()
                ->create([
                    'user_id' => $user->id,
                ]);
            $articles->push($article);
        }

        // assign a city and lat lng for each article created
        foreach ($articles as $article) {
            $article->location()->create([
                'name' => 'Location',
                'address' => 'Address',
                'lat' => 3.0254065,
                'lng' => 101.5814762,
                'address_2' => 'Address 2',
                'city' => 'Kuala Lumpur',
                'state_id' => State::where('name', 'Selangor')->first()->id,
                'country_id' => Country::where('name', 'Malaysia')->first()->id,
                'zip_code' => '123456'
            ]);
        }
         // check if /api/articles/cities have one city
         $response = $this->getJson('/api/v1/article_cities');
         $response->assertStatus(200)
             ->assertJsonStructure([
                 'cities',
             ]);
         $this->assertCount(1, $response->json('cities'));

         // assert city is Kuala Lumpur
         $this->assertEquals('Kuala Lumpur', $response->json('cities.0'));

         // my location: 3.013814, 101.622510
        $articlesWithinLatLng = $this->getJson('/api/v1/articles?lat=3.0254065&lng=101.5814762');
        $articleIdsWithinLatLng =collect($articlesWithinLatLng['data'])->pluck('id')->toArray();

        // assert data is 5 as its nearby
        $this->assertCount(5, $articlesWithinLatLng->json('data'));

        // 2. Bookmark all 5 articles by logged in user
        foreach ($articles as $article) {
            $bookmarks = $this->postJson('/api/v1/interactions', [
                'interactable' => 'article',
                'type' => 'bookmark',
                'id' => $article->id,

            ]);
            // assert json response
            $bookmarks->assertStatus(200)
                ->assertJsonStructure([
                    'interaction',
                ]);
        }

        // 3. Create 1 article by logged in user with Location Tagged
        // upload images first
        $response = $this->json('POST', '/api/v1/articles/gallery', [
            'images' => UploadedFile::fake()->image('test.jpg')
        ]);

        // create article category factory
        $categories = \App\Models\ArticleCategory::factory()->count(2)->create();

        // get ids array out of response json uploaded
        $image_ids = array_column($response->json('uploaded'), 'id');

        $authUserArticle = $this->postJson('/api/v1/articles', [
            'title' => 'Test Article with Images',
            'body' => 'Test Article Body',
            'type' => 'multimedia',
            'published_at' => now(),
            'status' => 1,
            'published_at' => now()->toDateTimeString(),
            'tags' => ['#test', '#test2'],
            'categories' => $categories->pluck('id')->toArray(),
            'images' => $image_ids,
            'location' => [
                'name' => 'Test Location',
                'address' => 'Test Address',
                'lat' => 1.234,
                'lng' => 1.234,
                'address_2' => 'Test Address 2',
                'city' => 'Test City',
                'state' => 'Selangor',
                'postcode' => '123456',
                'rating' => 4
            ]
        ]);
        $authUserArticle->assertStatus(200)
            ->assertJsonStructure([
                'message',
                'article',
            ]);

        $authUserArticleId = $authUserArticle->json('article.id');

        // 4. Attach 5 followings to logged-in user
        foreach ($users as $user) {
            // acting as logged in user, follow each of the users created
            $follow = $this->postJson('/api/v1/user/follow', [
                'user_id' => $user->id,
            ]);
            $follow->assertStatus(200)
                ->assertJsonStructure([
                    'message'
                ]);
            $this->assertDatabaseHas('users_followings', [
                'user_id' => $this->user->id,
                'following_id' => $user->id,
            ]);
        }

        $followings = $this->getJson('/api/v1/user/followings');

        $followings->assertStatus(200)
            ->assertJsonStructure([
                'data'
            ]);
        // assert should be 5 followings
        $this->assertCount(5, $followings->json()['data']);

        // check if followings of logged in user id are same as the users id
        $this->assertEqualsCanonicalizing(
            $users->pluck('id')->toArray(),
            collect($followings->json()['data'])->pluck('id')->toArray()
        );

        // ensure json data each user is_following is true
        foreach ($followings->json()['data'] as $user) {
            $this->assertTrue($user['is_following']);
        }

        // 5. Other user like and comment on article of logged-in user
        Sanctum::actingAs($users->first(), ['*']);
        $response = $this->postJson('/api/v1/interactions', [
            'id' => $authUserArticleId,
            'interactable' => Article::class,
            'type' => 'like',
        ]);
        $response->assertStatus(200);

        $comment = $this->postJson('/api/v1/comments', [
            'id' => $authUserArticleId,
            'type' => 'article',
            'body' => 'test comment',
            'parent_id' => null
        ]);
        $comment->assertStatus(200)
        ->assertJsonStructure([
            'comment'
        ]);
        $this->assertDatabaseHas('comments', [
            'body' => 'test comment',
            'commentable_id' => $authUserArticleId,
            'commentable_type' => Article::class,
            'user_id' => $users->first()->id,
        ]);

        // switch back to $this->user
        $this->actingAs($this->user);

        // 7. Logged-in user like and comment article of other user
        $response = $this->postJson('/api/v1/interactions', [
            'id' => $articles->first(),
            'interactable' => Article::class,
            'type' => 'like',
        ]);

        $authUserComment = $this->postJson('/api/v1/comments', [
            'id' => $articles->first(),
            'type' => 'article',
            'body' => 'test comment by auth user',
            'parent_id' => null
        ]);

        // 8. Update the article
        // create 3 more different categories
        $categories_new = \App\Models\ArticleCategory::factory()->count(3)->create();

        $response = $this->putJson('/api/v1/articles/' . $authUserArticleId, [
            'body' => 'Test Article Body',
            'status' => 1, // unpublished
            'categories' => $categories_new->pluck('id')->toArray(),
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'message'
            ]);

        $updatedArticle = Article::find($authUserArticleId);
        $this->assertModelExists($updatedArticle);
        $this->assertEquals($updatedArticle->id, $authUserArticleId);
        $this->assertEquals($updatedArticle->user_id, $this->user->id);
        $this->assertEquals($updatedArticle->body, 'Test Article Body');
        $this->assertEquals($updatedArticle->status, 1);

        $article = Article::find($authUserArticleId);

        // assert article has categories (new)
        $this->assertTrue($article->categories->pluck('id')->toArray() == $categories_new->pluck('id')->toArray());

        // 9. Get articles for homepage, and my_bookmarks
        // whitelist the user to see the article
        ArticleFeedWhitelistUser::create([
            'user_id' => $this->user->id,
            'created_by_id' => $article->user_id,
        ]);

        $homepageArticles = $this->getJson('/api/v1/articles?include_own_article=1');
        $this->assertEquals(6, $homepageArticles->json('meta.total'));

        $homepageArticleData = $homepageArticles->json('data');

        // CORE TEST: Get articles for my_profile and assert data returned same as in homepage
        $myArticles = $this->getJson('/api/v1/articles/my_articles');
        $this->assertEquals(1, $myArticles->json('meta.total'));
        $this->assertDatabaseHas('articles', [
            'title' => 'Test Article with Images',
            'body' => 'Test Article Body',
            'type' => 'multimedia',
            'status' => 1,
            'user_id' => $this->user->id,
        ]);
        $this->assertEquals(1, $myArticles['data'][0]['count']['comments']);
        $this->assertEquals(1, $myArticles['data'][0]['count']['likes']);
        $this->assertEquals(5, $myArticles['data'][0]['user']['following_count']);

        // test get article by id
        $response = $this->getJson('/api/v1/articles/' . $authUserArticleId);
        $response->assertStatus(200)
            ->assertJsonStructure([
                'article' => [
                    'location',
                ]
            ]);

        // check location data is correct
        $this->assertEquals('Test Location', $myArticles['data'][0]['location']['name']);

        // check if /api/articles/cities have one city
        $response = $this->getJson('/api/v1/article_cities');
        $response->assertStatus(200)
            ->assertJsonStructure([
                'cities',
            ]);
        $this->assertCount(2, $response->json('cities'));

        // assert city is Kuala Lumpur and Test City
        $this->assertEquals(array_values(['Kuala Lumpur','Test City']), array_values($response->json('cities')));

        $myArticleData = $myArticles->json('data');

        // Filter homepage articles created by $this->user
        $homepageArticlesCreatedByUser = collect($homepageArticleData)->filter(function ($article) {
            return $article['user']['id'] === $this->user->id;
        })->toArray();

        // Assertions that data returned from /articles created by $this->user is same as data returned from /my_articles
        $this->assertEquals(array_values($homepageArticlesCreatedByUser), array_values($myArticleData));

        // CORE TEST: Get my_bookmarks articles and assert data returned same as in homepage
        $myBookmarks = $this->getJson('/api/v1/articles/my_bookmarks');
        $myBookmarks->assertStatus(200)
            ->assertJsonStructure([
                'data',
            ]);

        $this->assertEquals(5, $myBookmarks->json('meta.total'));

        $myBookmarkIds = collect($myBookmarks['data'])->pluck('id')->toArray();

        // Filter homepage articles bookmarked by $this->user
        $homepageArticlesBookmarkedByUser = collect($homepageArticleData)->filter(function ($article) use ($myBookmarkIds) {
            return in_array($article['id'], $myBookmarkIds);
        })->toArray();

        $myBookmarksArray = $myBookmarks->json();

        // Assertions that data returned from /articles bookmarked by $this->user is same as data returned from /my_bookmarks
        foreach ($myBookmarksArray['data'] as $bookmark) {
            $articleId = $bookmark['id'];

            // Get the corresponding article with the same ID from $homepageArticlesBookmarkedByUser
            $matchingArticle = array_values(array_filter($homepageArticlesBookmarkedByUser, function($a) use ($articleId) {
                return $a['id'] === $articleId;
            }))[0];

            // Assert location data
            $this->assertEquals($bookmark['location'], $matchingArticle['location']);

            // Assert count data
            $this->assertEquals($bookmark['count'], $matchingArticle['count']);

            // Assert user_liked and user_bookmarked
            $this->assertEquals($bookmark['user_liked'], $matchingArticle['user_liked']);
            $this->assertEquals($bookmark['user_bookmarked'], $matchingArticle['user_bookmarked']);
        }

        // CORE TEST: GetArticlesWithintMyLatLng and assert data returned same as in homepage
        $articlesWithinLatLng = $this->getJson('/api/v1/articles?lat=3.0254065&lng=101.5814762');
        $articleIdsWithinLatLng = collect($articlesWithinLatLng['data'])->pluck('id')->toArray();

        // Filter homepage articles with same Lat Lng query
        $homePageArticlesWithinUserLatLng = collect($homepageArticleData)->filter(function ($article) use ($articleIdsWithinLatLng) {
            return in_array($article['id'], $articleIdsWithinLatLng);
        })->toArray();

        $articlesWithinLatLngArray = $articlesWithinLatLng->json();

        // Assertions that data returned from /articles is same as data returned from GetArticlesWithintMyLatLng
        foreach ($articlesWithinLatLngArray['data'] as $article) {
            $articleId = $article['id'];

            // Get the corresponding article with the same ID from $homePageArticlesWithinUserLatLng
            $matchingArticle = array_values(array_filter($homePageArticlesWithinUserLatLng, function($a) use ($articleId) {
                return $a['id'] === $articleId;
            }))[0];

            // Assert location data
            $this->assertEquals($article['location'], $matchingArticle['location']);

            // Assert count data
            $this->assertEquals($article['count'], $matchingArticle['count']);

            // Assert user_liked and user_bookmarked
            $this->assertEquals($article['user_liked'], $matchingArticle['user_liked']);
            $this->assertEquals($article['user_bookmarked'], $matchingArticle['user_bookmarked']);
        }

        // CORE TEST: GetArticlesForHomeFilteredByCategoryIdsByLoggedInUser and assert data returned same as in homepage
        collect($articles->take(3))->each(function ($article) use ($categories) {
            $article->categories()->attach($categories->pluck('id')->toArray());
        });

        $articlesWithCategories = $this->getJson('/api/v1/articles?category_ids=' . $categories->pluck('id')->implode(','));
        $articleIdsWithCategories = collect($articlesWithCategories['data'])->pluck('id')->toArray();

        $articlesWithCategories->assertStatus(200)
            ->assertJsonStructure([
                'data',
            ]);

        // ensure only 3 articles got taken
        $this->assertEquals(3, $articlesWithCategories->json('meta.total'));

        // check 3 articles have correct article categories ids
        collect($articlesWithCategories->json('data'))->each(function ($article) use ($categories) {
            // get article categories ids from $article['categories']
            $article_categories_id = collect($article['categories'])->pluck('id')->toArray();
            $this->assertEquals($categories->pluck('id')->toArray(), $article_categories_id);
        });

        // Filter homepage articles with categories
        $homepageArticlesWithCategories = collect($homepageArticleData)->filter(function ($article) use ($articleIdsWithCategories) {
            return in_array($article['id'], $articleIdsWithCategories);
        })->toArray();

        $articlesWithCategoriesArray = $articlesWithCategories->json();

        // Assertions that data returned from /articles filtered by categories is same as in homepage
        foreach ($articlesWithCategoriesArray['data'] as $article) {
            $articleId = $article['id'];

            // Get the corresponding article with the same ID from $homepageArticlesWithCategories
            $matchingArticle = array_values(array_filter($homepageArticlesWithCategories, function($a) use ($articleId) {
                return $a['id'] === $articleId;
            }))[0];

            // Assert location data
            $this->assertEquals($article['location'], $matchingArticle['location']);

            // Assert count data
            $this->assertEquals($article['count'], $matchingArticle['count']);

            // Assert user_liked and user_bookmarked
            $this->assertEquals($article['user_liked'], $matchingArticle['user_liked']);
            $this->assertEquals($article['user_bookmarked'], $matchingArticle['user_bookmarked']);
        }
    }

    public function testWhitelistUserForArticleFeed()
    {
        $response = $this->json('POST', '/api/v1/articles/gallery', [
            'images' => UploadedFile::fake()->image('test.jpg')
        ]);

        // create article category factory
        $categories = \App\Models\ArticleCategory::factory()->count(2)->create();

        // get ids array out of response json uploaded
        $image_ids = array_column($response->json('uploaded'), 'id');

        // by default entire server new posts is hidden from home as Setting is set to true
        // create a new article by a user
        $response = $this->postJson('/api/v1/articles', [
            'title' => 'Test Article with Images',
            'body' => 'Test Article Body',
            'type' => 'multimedia',
            'published_at' => now(),
            'status' => 1,
            'published_at' => now()->toDateTimeString(),
            'tags' => ['#test', '#test2'],
            'categories' => $categories->pluck('id')->toArray(),
            'images' => $image_ids
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'message',
                'article',
            ]);

        // login as another user
        $otherUser = User::factory()->create();
        $this->actingAs($otherUser);

        // get id
        $article = Article::find($response->json('article.id'));

        // get articles from home should not show this user's article since by default everyone is
        $response = $this->getJson('/api/v1/articles')
            ->assertStatus(200);

        $this->assertNotContains($article->id, collect($response->json('data'))->pluck('id')->toArray());

        // update article hidden_from_home to false
        Article::where('id', $article->id)->update([
            'hidden_from_home' => false
        ]);

        // query again see, should appear
        $response = $this->getJson('/api/v1/articles')
            ->assertStatus(200);

        $this->assertContains($article->id, collect($response->json('data'))->pluck('id')->toArray());


        // revert hidden_from_home to true
        Article::where('id', $article->id)->update([
            'hidden_from_home' => true
        ]);

        // whitelist this user
        ArticleFeedWhitelistUser::create([
            'user_id' => $this->user->id,
            'created_by_id' => $this->user->id,
        ]);

        // get articles from home should show see the article even though hidden from home is true
        $response = $this->getJson('/api/v1/articles')
            ->assertStatus(200);

        $this->assertContains($article->id, collect($response->json('data'))->pluck('id')->toArray());

        // if get is_following means all is shown, use this user follow article author first
        $response = $this->postJson('/api/v1/user/follow', [
            'user_id' => $this->user->id,
        ]);
        $response->assertStatus(200);

        // delete from whitelist
        ArticleFeedWhitelistUser::where('user_id', $this->user->id)->delete();

        // act as other user
        $this->actingAs($otherUser);

        // get articles from home should show see the article even though hidden from home is true
        $response = $this->getJson('/api/v1/articles?following_only=1')
            ->assertStatus(200);

        $this->assertContains($article->id, collect($response->json('data'))->pluck('id')->toArray());
    }

    public function testArticleTagsCreatedCountReflect()
    {
        // Create the first article with the tag '#test'
        $response1 = $this->postJson('/api/v1/articles', [
            'title' => 'First Article with Tag',
            'body' => 'First Article Body',
            'type' => 'multimedia',
            'published_at' => now()->toDateTimeString(),
            'status' => 1,
            'tags' => ['#test'],
        ]);

        $response1->assertStatus(200)
            ->assertJsonStructure([
                'message',
                'article',
            ]);

        // Create the second article with the same tag '#test'
        $response2 = $this->postJson('/api/v1/articles', [
            'title' => 'Second Article with Tag',
            'body' => 'Second Article Body',
            'type' => 'multimedia',
            'published_at' => now()->toDateTimeString(),
            'status' => 1,
            'tags' => ['#test'],
        ]);

        $response2->assertStatus(200)
            ->assertJsonStructure([
                'message',
                'article',
            ]);

        // Verify both articles are in the database
        $this->assertDatabaseHas('articles', [
            'title' => 'First Article with Tag',
            'body' => 'First Article Body',
            'type' => 'multimedia',
            'status' => 1,
            'user_id' => $this->user->id,
        ]);

        $this->assertDatabaseHas('articles', [
            'title' => 'Second Article with Tag',
            'body' => 'Second Article Body',
            'type' => 'multimedia',
            'status' => 1,
            'user_id' => $this->user->id,
        ]);

        // Retrieve the tag from the database
        $articleTag = ArticleTag::where('name', '#test')->first();

        // Ensure the tag is associated with two articles
        $articlesWithTag = $articleTag ? $articleTag->articles : collect();

        // Verify the number of articles associated with the tag '#test' is 2
        $this->assertCount(2, $articlesWithTag, 'There should be 2 articles with tag #test');
    }

    /**
     * Test creating article with mall outlet location not attaching to mall location
     * /api/v1/articles
     */
    public function testCreateArticleWithMallOutletLocation()
    {
        // ensure countries and states are seeded first
        $this->seed(CountriesTableSeeder::class);
        $this->seed(StatesTableSeeder::class);

        // 1. First create a mall location
        $mallLocation = Location::create([
            'name' => 'Sunway Pyramid',
            'google_id' => 'mall_google_id_123',
            'lat' => 3.073210,
            'lng' => 101.607140,
            'address' => 'No. 3, Jalan PJS 11/15, Bandar Sunway',
            'address_2' => '',
            'city' => 'Petaling Jaya',
            'state_id' => State::where('name', 'Selangor')->first()->id,
            'country_id' => Country::where('name', 'Malaysia')->first()->id,
            'zip_code' => '47500',
            'is_mall' => true
        ]);

        // 2. Create an outlet location (same coordinates as mall)
        $outletLocation = Location::create([
            'name' => 'Chagee @ Sunway Pyramid',
            'google_id' => 'outlet_google_id_456',
            'lat' => 3.073210, // Same coordinates as mall
            'lng' => 101.607140, // Same coordinates as mall
            'address' => 'LG2.130, Sunway Pyramid',
            'address_2' => 'No. 3, Jalan PJS 11/15, Bandar Sunway',
            'city' => 'Petaling Jaya',
            'state_id' => State::where('name', 'Selangor')->first()->id,
            'country_id' => Country::where('name', 'Malaysia')->first()->id,
            'zip_code' => '47500',
            'is_mall' => false
        ]);

        // 3. Create article with outlet location
        $response = $this->postJson('/api/v1/articles', [
            'title' => 'Test Article at Mall Outlet',
            'body' => 'Test Article Body',
            'type' => 'multimedia',
            'published_at' => now(),
            'status' => 1,
            'published_at' => now()->toDateTimeString(),
            'tags' => ['#mall', '#cafe'],
            'location' => [
                'name' => 'Chagee @ Sunway Pyramid',
                'address' => 'LG2.130, Sunway Pyramid',
                'lat' => 3.073210,
                'lng' => 101.607140,
                'address_2' => 'No. 3, Jalan PJS 11/15, Bandar Sunway',
                'city' => 'Petaling Jaya',
                'state' => 'Selangor',
                'postcode' => '47500',
                'rating' => 4
            ]
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'message',
                'article' => [
                    'location' => [
                        'name',
                        'address',
                        'lat',
                        'lng'
                    ]
                ]
            ]);

        // 4. Assert outlet location was correctly attached
        $articleId = $response->json('article.id');
        $article = Article::find($articleId);

        $attachedLocation = $article->location()->first();

        // Assert the correct location was attached
        $this->assertNotNull($attachedLocation);
        $this->assertEquals('Chagee @ Sunway Pyramid', $attachedLocation->name);
        $this->assertEquals('LG2.130, Sunway Pyramid', $attachedLocation->address);
        $this->assertEquals(3.073210, $attachedLocation->lat);
        $this->assertEquals(101.607140, $attachedLocation->lng);

        // Assert it didn't attach to the mall location
        $this->assertNotEquals($mallLocation->id, $attachedLocation->id);
        $this->assertEquals($outletLocation->id, $attachedLocation->id);

        // Assert rating was correctly added
        $this->assertDatabaseHas('location_ratings', [
            'location_id' => $attachedLocation->id,
            'user_id' => $this->user->id,
            'rating' => 4
        ]);

        // 5. Test retrieving the article with location
        $getResponse = $this->getJson('/api/v1/articles/' . $articleId);

        $getResponse->assertStatus(200)
            ->assertJson([
                'article' => [
                    'location' => [
                        'name' => 'Chagee @ Sunway Pyramid',
                        'lat' => 3.07321,
                        'lng' => 101.60714,
                    ]
                ]
            ]);
    }

    /**
     * Test updating article with mall outlet location
     * /api/v1/articles/{id}
     */
    public function testUpdateArticleWithMallOutletLocation()
    {
        // ensure countries and states are seeded first
        $this->seed(CountriesTableSeeder::class);
        $this->seed(StatesTableSeeder::class);

        // 1. Create initial article with a regular location
        $article = Article::factory()->create([
            'user_id' => $this->user->id
        ]);

        $initialLocation = Location::create([
            'name' => 'Regular Place',
            'lat' => 3.123456,
            'lng' => 101.123456,
            'address' => 'Test Address',
            'city' => 'Petaling Jaya',
            'state_id' => State::where('name', 'Selangor')->first()->id,
            'country_id' => Country::where('name', 'Malaysia')->first()->id,
            'zip_code' => '47500',
            'is_mall' => false
        ]);

        $article->location()->attach($initialLocation->id);

        // 2. Create mall and outlet locations
        $mallLocation = Location::create([
            'name' => 'Sunway Pyramid',
            'google_id' => 'mall_google_id_789',
            'lat' => 3.073210,
            'lng' => 101.607140,
            'address' => 'No. 3, Jalan PJS 11/15, Bandar Sunway',
            'city' => 'Petaling Jaya',
            'state_id' => State::where('name', 'Selangor')->first()->id,
            'country_id' => Country::where('name', 'Malaysia')->first()->id,
            'zip_code' => '47500',
            'is_mall' => true
        ]);

        $outletLocation = Location::create([
            'name' => 'Chagee @ Sunway Pyramid',
            'google_id' => 'outlet_google_id_101',
            'lat' => 3.073210,
            'lng' => 101.607140,
            'address' => 'LG2.130, Sunway Pyramid',
            'address_2' => 'No. 3, Jalan PJS 11/15, Bandar Sunway',
            'city' => 'Petaling Jaya',
            'state_id' => State::where('name', 'Selangor')->first()->id,
            'country_id' => Country::where('name', 'Malaysia')->first()->id,
            'zip_code' => '47500',
            'is_mall' => false
        ]);

        // 3. Update article with outlet location
        $response = $this->putJson('/api/v1/articles/' . $article->id, [
            'body' => 'Updated Article Body',
            'status' => 1,
            'location' => [
                'name' => 'Chagee @ Sunway Pyramid',
                'address' => 'LG2.130, Sunway Pyramid',
                'lat' => 3.073210,
                'lng' => 101.607140,
                'address_2' => 'No. 3, Jalan PJS 11/15, Bandar Sunway',
                'city' => 'Petaling Jaya',
                'state' => 'Selangor',
                'postcode' => '47500',
                'rating' => 5
            ]
        ]);

        $response->assertStatus(200);

        // 4. Assert correct location was attached
        $article->refresh();
        $attachedLocation = $article->location()->first();

        $this->assertNotNull($attachedLocation);
        $this->assertEquals('Chagee @ Sunway Pyramid', $attachedLocation->name);
        $this->assertNotEquals($mallLocation->id, $attachedLocation->id);
        $this->assertEquals($outletLocation->id, $attachedLocation->id);

        // 5. Assert rating was updated
        $this->assertDatabaseHas('location_ratings', [
            'location_id' => $attachedLocation->id,
            'user_id' => $this->user->id,
            'rating' => 5
        ]);

        // 6. Verify initial location was detached
        $this->assertEquals(1, $article->location()->count());
        $this->assertNotEquals($initialLocation->id, $attachedLocation->id);
    }

    // public function testArticleNotInterestedByUser()
    // {
    //     // create a user
    //     $user = User::factory()->create();
    //     // create 10 articles by this user
    //     $articles = Article::factory()->count(10)->published()
    //         ->create([
    //             'user_id' => $user->id,
    //         ]);

    //     // logged in user not interested in 5 of the articles
    //     $notInterestedArticleIds = [];
    //     foreach($articles->take(5) as $article) {
    //         $response = $this->postJson('/api/v1/articles/not_interested', [
    //             'article_id' => $article->id,
    //         ]);
    //         $notInterestedArticleIds[] = $article->id;

    //         $response->assertStatus(200);
    //     }

    //     // get articles and check whether the data.id dosent have not interested articles
    //     $response = $this->getJson('/api/v1/articles');
    //     $response->assertStatus(200)
    //         ->assertJsonStructure([
    //             'data'
    //         ]);

    //     // check each article id is not in $notInterestedArticleIds
    //     foreach($response->json('data') as $article) {
    //         $this->assertNotContains($article['id'], $notInterestedArticleIds);
    //     }
    // }
}
