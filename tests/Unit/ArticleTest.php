<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Models\Article;
use App\Models\ArticleCategory;
use App\Models\ArticleTag;
use App\Models\Country;
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
        Sanctum::actingAs($this->user,['*']);
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
        collect($articles->take(5))->each(function($article) use ($categories) {
            $article->categories()->attach($categories->pluck('id')->toArray());
        });

        $response = $this->getJson('/api/v1/articles?category_ids='.$categories->pluck('id')->implode(','));

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data',
            ]);

        // ensure only 5 articles got taken
        $this->assertEquals(5, $response->json('meta.total'));

        // check 5 articles have correct article categories ids
        collect($response->json('data'))->each(function($article) use ($categories) {
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
        collect($articles->take(5))->each(function($article) use ($tags) {
            $article->tags()->attach($tags->pluck('id')->toArray());
        });

        $response = $this->getJson('/api/v1/articles?tag_ids='.$tags->pluck('id')->implode(','));

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data',
            ]);

        // ensure only 5 articles got taken
        $this->assertEquals(5, $response->json('meta.total'));

        // check 5 articles have correct article tags ids
        collect($response->json('data'))->each(function($article) use ($tags) {
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
        $response = $this->getJson('/api/v1/articles/'.$secondArticle->id);
        
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
        $response = $this->getJson('/api/v1/articles/my_articles?user_id='.$user->id);

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
        $this->assertDatabaseHas('articles_article_tags',[
            'article_id' => $article_id,
            'article_tag_id' => ArticleTag::where('name', '#test')->first()->id,
        ]);
        $this->assertDatabaseHas('articles_article_tags',[
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
        $this->assertDatabaseHas('articles_article_tags',[
            'article_id' => $article_id,
            'article_tag_id' => ArticleTag::where('name', '#test')->first()->id,
        ]);
        $this->assertDatabaseHas('articles_article_tags',[
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

        $response = $this->putJson('/api/v1/articles/'.$article->id, [
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
        $response = $this->getJson('/api/v1/articles/'.$article->id);
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
        $response = $this->getJson('/api/v1/articles/'.$article->id);
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
        Sanctum::actingAs($user,['*']);

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

        $response = $this->putJson('/api/v1/articles/'.$article->id, [
            'body' => 'Test Article Body',
            'status' => 1, // unpublished
            'categories' => implode(',', $categories_new->pluck('id')->toArray()),
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

        Log::info($article->categories->pluck('id')->toArray());

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
        $response = $this->getJson('/api/v1/articles?article_ids='.implode(',', $articles->pluck('id')->toArray()));

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
        foreach($users as $user) {
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
        $response = $this->getJson('/api/v1/articles/'.$article_id);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'article' => [
                    'location',
                ]
            ]);
        // check location data is correct
        $this->assertEquals('Test Location', $response->json('article.location.name'));
        $this->assertEquals('Test Address', $response->json('article.location.address'));
        $this->assertEquals('Test Address 2', $response->json('article.location.address_2'));
        $this->assertEquals('Test City', $response->json('article.location.city'));
        $this->assertEquals('Selangor', $response->json('article.location.state.name'));
        $this->assertEquals('123456', $response->json('article.location.postcode'));
        $this->assertEquals(4.0, $response->json('article.location.average_ratings'));

        // check if /api/articles/cities have one city
        $response = $this->getJson('/api/v1/articles/cities');
        $response->assertStatus(200)
            ->assertJsonStructure([
                'cities',
            ]);

        $this->assertCount(1, $response->json('cities'));

        // assert city is Test City
        $this->assertEquals('Test City', $response->json('cities.0'));
    }

    /**
     * Test Create Article with followers tagged
     * /api/v1/articles
     */
    public function testCreateArticleWithFollowersTagged()
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
        foreach($users as $user) {
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
        $response = $this->getJson('/api/v1/articles/'.$response->json('article.id'));

        $response->assertStatus(200)
            ->assertJsonStructure([
                'article' => [
                    'tagged_users',
                ]
            ]);
        
        // loop each tagged_users check match with $users
        foreach($response->json('article.tagged_users') as $tagged_user) {
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
        
        // create 10 articles
        $articles = Article::factory()->count(10)->published()
            ->create([
                'user_id' => $this->user->id,
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
        foreach($articles as $article) {
            $article->location()->create([
                'name' => 'Test Location',
                'address' => 'Test Address',
                'lat' => 1.234,
                'lng' => 1.234,
                'address_2' => 'Test Address 2',
                // randomize cities
                'city' => $cities[rand(1,2)],
                'state_id' => State::where('name', 'Selangor')->first()->id,
                'country_id' => Country::where('name', 'Malaysia')->first()->id,
                'zip_code' => '123456'
            ]);
        }

        // get articles by city
        $response = $this->getJson('/api/v1/articles?city='.$cities[0]);
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
         
         // create 10 articles
         $articles = Article::factory()->count(10)->published()
             ->create([
                 'user_id' => $this->user->id,
             ]);
 
         // 3 cities of Klang valley
         $cities = ['Kuala Lumpur', 'Petaling Jaya', 'Shah Alam'];
         foreach($articles as $article) {
            $article->location()->create([
                'name' => 'Test Location',
                'address' => 'Test Address',
                'lat' => 3.0130517,
                'lng' => 101.6199414,
                'address_2' => 'Test Address 2',
                'city' => $cities[0], 
                'state_id' => State::where('name', 'Selangor')->first()->id,
                'country_id' => Country::where('name', 'Malaysia')->first()->id,
                'zip_code' => '123456'
            ]);
        }

        // my location: 3.013814, 101.622510
        $response = $this->getJson('/api/v1/articles?lat=3.013814&lng=101.622510');
        
        // assert data is 10 as its nearby
        $this->assertCount(10, $response->json('data'));
    }
}
