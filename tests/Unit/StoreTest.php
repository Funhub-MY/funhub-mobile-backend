<?php

namespace Tests\Unit;

use App\Models\Article;
use App\Models\Location;
use App\Models\Merchant;
use App\Models\MerchantOffer;
use App\Models\RatingCategory;
use App\Models\State;
use App\Models\Store;
use App\Models\StoreRating;
use App\Models\User;
use App\Models\UserFollowing;
use Database\Seeders\CountriesTableSeeder;
use Database\Seeders\StatesTableSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class StoreTest extends TestCase
{
    use RefreshDatabase;

    protected $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->refreshDatabase();

        // Mock logged-in user and get token
        $this->user = User::factory()->create();
        Sanctum::actingAs($this->user, ['*']);

        // create a merchant for this user
        $merchant = Merchant::factory()->create([
            'user_id' => $this->user->id,
            'status' => Merchant::STATUS_APPROVED,
        ]);

        // Seed states and countries
        $this->seed(CountriesTableSeeder::class);
        $this->seed(StatesTableSeeder::class);
    }

    public function testGetStores()
    {
        $stores = Store::factory()->count(5)->create([
            'user_id' => $this->user->id,
        ]);

        $response = $this->getJson('/api/v1/stores');

        $response->assertStatus(200);
        // ensure there's five stores returned
        $this->assertCount(5, $response['data']);

        // // create a new user and merchant which is unapproved
        // $user2 = User::factory()->create();
        // $merchant2 = Merchant::factory()->create([
        //     'user_id' => $user2->id,
        //     'status' => Merchant::STATUS_PENDING,
        // ]);

        // // create 5 more unapproved
        // Store::factory()->count(5)->create([
        //     'user_id' => $user2,
        // ]);

        // $response = $this->getJson('/api/v1/stores');

        // // ensure still 5
        // $this->assertCount(5, $response['data']);
    }

    public function testStoreCategories()
    {
        $store = Store::factory()->create([
            'user_id' => $this->user->id,
        ]);
        $categories = \App\Models\MerchantCategory::factory()->count(5)->create([
            'user_id' => $this->user->id,
        ]);

        $store->categories()->attach($categories->pluck('id'));

        // create another one store without categories
        $store2 = Store::factory()->create([
            'user_id' => $this->user->id,
        ]);

        $response = $this->getJson("/api/v1/stores?categories_id={$categories->pluck('id')->implode(',')}");

        $response->assertStatus(200);

        // ensure returned data only store with categories
        $this->assertEquals(1, $response->json()['meta']['total']);

        // check if the data first ->id is $store->id
        $this->assertEquals($store->id, $response->json()['data'][0]['id']);

        foreach ($categories as $category) {
            // assert database has this category tied to store
            $this->assertDatabaseHas('merchant_category_stores', [
                'store_id' => $store->id,
                'merchant_category_id' => $category->id,
            ]);
        }
    }

    public function testGetStoresByIds()
    {
        $stores = Store::factory()->count(5)->create([
            'user_id' => $this->user->id,
        ]);
        $response = $this->getJson('/api/v1/stores?store_ids=' . $stores->pluck('id')->implode(','));

        $response->assertStatus(200);
        // ensure there's five stores returned
        $this->assertCount(5, $response->json('data'));
    }

    public function testGetStoreRatings()
    {
        $store = Store::factory()->create([
            'user_id' => $this->user->id,
        ]);
        $ratings = StoreRating::factory()->count(5)->create(['store_id' => $store->id]);

        $response = $this->getJson("/api/v1/stores/{$store->id}/ratings");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'store_id',
                        'user_id',
                        'rating',
                        'comment',
                        'likes_count',
                        'dislikes_count',
                        'created_at',
                        'updated_at',
                    ],
                ],
            ]);

        foreach ($ratings as $rating) {
            $this->assertDatabaseHas('store_ratings', [
                'id' => $rating->id,
                'store_id' => $store->id,
                'user_id' => $rating->user_id,
                'rating' => $rating->rating,
                'comment' => $rating->comment,
            ]);
        }
    }

    public function testPostStoreRatings()
    {
        $store = Store::factory()->create();
        $ratingCategories = RatingCategory::factory()->count(3)->create();

        $ratingCategoriesIds = $ratingCategories->pluck('id')->toArray();
        $ratingData = [
            'rating' => 4,
            'comment' => 'Great service!',
            'rating_category_ids' => implode(',', $ratingCategoriesIds),
        ];

        $response = $this->postJson("/api/v1/stores/{$store->id}/ratings", $ratingData);

        $response->assertJsonStructure([
                'data' => [
                    'id',
                    'store_id',
                    'user_id',
                    'rating',
                    'comment',
                    'likes_count',
                    'dislikes_count',
                    'created_at',
                    'updated_at',
                ],
            ]);

        // assert that the rating was created
        $this->assertDatabaseHas('store_ratings', [
            'store_id' => $store->id,
            'user_id' => $this->user->id,
            'rating' => $ratingData['rating'],
            'comment' => $ratingData['comment'],
        ]);

        // assert rating categories attached to this rating_id
        foreach ($ratingCategories as $category) {
            $this->assertDatabaseHas('rating_categories_store_ratings', [
                'store_rating_id' => $response['data']['id'],
                'rating_category_id' => $category->id,
                'user_id' => $this->user->id,
            ]);
        }
    }

    public function testGetMerchantMenus()
    {
        $store = Store::factory()->create([
            'user_id' => $this->user->id,
        ]);
        $merchant = $store->merchant;

        $menus = [
            UploadedFile::fake()->create('menu1.pdf'),
            UploadedFile::fake()->create('menu2.pdf'),
            UploadedFile::fake()->create('menu3.pdf'),
        ];

        foreach ($menus as $menu) {
            $merchant->addMedia($menu)->toMediaCollection(Merchant::MEDIA_COLLECTION_MENUS);
        }

        $response = $this->getJson("/api/v1/stores/{$store->id}/menus");

        $response->assertStatus(200);

        // ensure there's three urls returned
        $this->assertCount(3, $response->json());
    }

    public function testGetRatingCategories()
    {
        $ratingCategories = RatingCategory::factory()->count(5)->create();

        $response = $this->getJson('/api/v1/stores/rating_categories');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'name',
                        'created_at',
                        'updated_at',
                    ],
                ],
            ]);

        // ensure there's five rating categories returned
        $this->assertCount(5, $response['data']);
    }

    public function testGetStoreMerchantOffers()
    {

        // create a store attached to $this->user
        $store = Store::factory()->create([
            'user_id' => $this->user->id,
        ]);

        $merchantOffer = MerchantOffer::factory()->published()->count(5)->for(
            $this->user
        )->create();

        $merchantOffer->each(function ($offer) use ($store) {
            $offer->stores()->attach($store->id);
        });

        // create a new user and merchant attached
        $user2 = User::factory()->create();
        $merchant2 = Merchant::factory()->create([
            'user_id' => $user2->id,
            'status' => Merchant::STATUS_APPROVED,
        ]);

        // create another store attached to user2
        $store2 = Store::factory()->create([
            'user_id' => $user2->id,
        ]);

        // create another offer attached with this user2
        $merchantOffer2 = MerchantOffer::factory()->published()->for(
            $user2
        )->create();
        // attach store2 to this offer2
        $merchantOffer2->stores()->attach($store2->id);

        $response = $this->getJson('/api/v1/merchant/offers?store_id='.$store->id);
        $response->assertStatus(200);
        // check if theres only 5 offer returned
        $this->assertEquals(5, $response->json()['meta']['total']);

        $response = $this->getJson('/api/v1/merchant/offers?store_id='.$store->id.', '.$store2->id);
        $response->assertStatus(200);
        // check if 6 offers returned
        $this->assertEquals(6, $response->json()['meta']['total']);
    }

    public function testFollowingCountOfAStore()
    {
        // following count is whoever im following has an article about this store

        // create a new user
        $user = User::factory()->create();

        // user this user follow the other $user
        $this->actingAs($this->user);
        $response = $this->postJson('/api/v1/user/follow', [
            'user_id' => $user->id,
        ]);
        $response->assertStatus(200);

        // acting as $user create an article about default store and merchant
        $this->actingAs($user); // act as newUser
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

        // create a merchant
        // act as $this->user
        $this->actingAs($this->user);

        $merchant = Merchant::factory()->create([
            'user_id' => $user->id,
            'status' => Merchant::STATUS_APPROVED,
        ]);

        // create store that has location attached with same location above
        $store = Store::factory()->create([
            'user_id' => $this->user,
            'state_id' => State::first()->id,
            'country_id' => 1,
            'lang' => 1.234,
            'long' => 1.234,
        ]);

        // create a Location to attach to this store
        $location = Location::where('name', 'Test Location')->first(); // must tag the same location

        $store->location()->attach($location->id);

        // use this->user follow the $user
        $this->actingAs($this->user);
        $response = $this->postJson('/api/v1/user/follow', [
            'user_id' => $user->id,
        ]);

        // clear cache to refresh the data
        $this->artisan('cache:clear');

        // get followings_been_here for the store
        $response = $this->getJson('/api/v1/stores/followings_been_here?store_ids='.$store->id);

        $response->assertJsonStructure([
            'data' => [
                [
                    'storeId',
                    'followingsBeenHere' => [
                        '*' => [
                            'id',
                            'name',
                            'username',
                            'avatar',
                            'avatar_thumb',
                            'has_avatar',
                        ],
                    ],
                ],
            ],
        ]);

        // assert followings_been_here is not empty
        $this->assertNotEmpty($response->json('data.0.followingsBeenHere'));

        // create a new user which is non-follower of $user then query the followings_been_here again
        $user2 = User::factory()->create();
        $this->actingAs($user2);
        $response = $this->getJson('/api/v1/stores/followings_been_here?store_ids='.$store->id);

        // assert followings_been_here is empty
        $this->assertEmpty($response->json('data.0.followingsBeenHere'));

        // if $this->user unfollows $user then followings_been_here should be empty
        $this->actingAs($this->user);
        $response = $this->postJson('/api/v1/user/unfollow', [
            'user_id' => $user->id,
        ]);

        // clear cache to refresh the data
        $this->artisan('cache:clear');

        $response = $this->getJson('/api/v1/stores/followings_been_here?store_ids='.$store->id);
        $this->assertEmpty($response->json('data.0.followingsBeenHere'));
    }

    public function testGetArticlesOfStores()
    {
        // create a new user
        $user = User::factory()->create();
        // acting as user
        $this->actingAs($user); // act as newUser
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

        // create another different location
        $response = $this->postJson('/api/v1/articles', [
            'title' => 'Test Article with Images 2',
            'body' => 'Test Article Body2',
            'type' => 'multimedia',
            'published_at' => now(),
            'status' => 1,
            'published_at' => now()->toDateTimeString(),
            'tags' => ['#test', '#test2'],
            'categories' => $categories->pluck('id')->toArray(),
            'images' => $image_ids,
            'location' => [
                'name' => 'Test Location 2',
                'address' => 'Test Address 2',
                'lat' => 1.211,
                'lng' => 1.211,
                'address_2' => 'Test Address 2',
                'city' => 'Test City',
                'state' => 'Selangor',
                'postcode' => '123456',
                'rating' => 4
            ]
        ]);

        // create a merchant
        // act as $this->user
        $this->actingAs($this->user);

        $merchant = Merchant::factory()->create([
            'user_id' => $user->id,
            'status' => Merchant::STATUS_APPROVED,
        ]);

        // create store that has location attached with same location above
        $store = Store::factory()->create([
            'user_id' => $this->user,
            'state_id' => State::first()->id,
            'country_id' => 1,
            'lang' => 1.234,
            'long' => 1.234,
        ]);

        // create a Location to attach to this store
        $location = Location::where('name', 'Test Location')->first();

        $store->location()->attach($location->id);

        // get ids of store using /stores/{id}/locations
        $response = $this->getJson("/api/v1/stores/locations?store_ids={$store->id}");
        $response->assertStatus(200);

        // asset response has data
        $this->assertArrayHasKey('data', $response->json());

        $location_ids = $response->json()['data'];
        // asset location_ids count is 1
        $this->assertCount(1, $location_ids);

        // get articles with /api/v1/articles?location_id=1
        $response = $this->getJson('/api/v1/articles?location_id=' . $location_ids[0]['id']);

        // make sure there's one article returned
        // 200 response
        $response->assertStatus(200);
        // assert response has data
        $this->assertArrayHasKey('data', $response->json());
        // assert meta.total is 1
        $this->assertEquals(1, $response->json()['meta']['total']);
    }

    // get stores by location id /api/v1/stores/stores_by_location
    public function testGetStoresByLocation()
    {
        // create a new user
        $user = User::factory()->create();
        // acting as user
        $this->actingAs($user); // act as newUser
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

        // attach store to same location as well
        $store = Store::factory()->create([
            'user_id' => $user->id,
            'state_id' => State::first()->id,
            'country_id' => 1,
            'lang' => 1.234,
            'long' => 1.234,
        ]);

        // create a Location to attach to this store
        $location = Location::where('name', 'Test Location')->first(); // must tag the same location
        $location->stores()->attach($store->id);

        $response = $this->getJson('/api/v1/stores');

        $response->assertStatus(200);

        // ensure there's one store returned
        $this->assertCount(1, $response->json()['data']);

        // asset data first store id is $store->id
        $this->assertEquals($store->id, $response->json()['data'][0]['id']);
    }
}
