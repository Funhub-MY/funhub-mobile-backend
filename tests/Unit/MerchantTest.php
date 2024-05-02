<?php

namespace Tests\Unit;

use App\Models\Location;
use App\Models\Merchant;
use App\Models\MerchantRating;
use App\Models\RatingCategory;
use App\Models\State;
use App\Models\Store;
use App\Models\User;
use Database\Seeders\CountriesTableSeeder;
use Database\Seeders\StatesTableSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MerchantTest extends TestCase
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

        // Seed states and countries
        $this->seed(CountriesTableSeeder::class);
        $this->seed(StatesTableSeeder::class);
    }

    public function testGetMerchants()
    {
        $merchants = Merchant::factory()->count(5)->create([
            'status' => Merchant::STATUS_APPROVED,
        ]);

        $response = $this->getJson('/api/v1/merchants');

        $response->assertStatus(200);
        // ensure there's five merchants returned
        $this->assertCount(5, $response['data']);

        // create 5 more unapproved
        Merchant::factory()->count(5)->create([
            'status' => Merchant::STATUS_PENDING,
        ]);

        $response = $this->getJson('/api/v1/merchants');

        // ensure still 5
        $this->assertCount(5, $response['data']);
    }

    public function testGetMerchantsByIds()
    {
        $merchants = Merchant::factory()->count(5)->create([
            'status' => Merchant::STATUS_APPROVED,
        ]);

        $response = $this->getJson('/api/v1/merchants', ['merchant_ids' => $merchants->pluck('id')->implode(',')]);

        $response->assertStatus(200);
        // ensure there's five merchants returned
        $this->assertCount(5, $response['data']);
    }

    public function testGetMerchantRatings()
    {
        $merchant = Merchant::factory()->create();
        $ratings = MerchantRating::factory()->count(5)->create(['merchant_id' => $merchant->id]);

        $response = $this->getJson("/api/v1/merchants/{$merchant->id}/ratings");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'merchant_id',
                        'user_id',
                        'user' => [
                            'id',
                            'name',
                            'username',
                            'avatar',
                            'avatar_thumb',
                        ],
                        'rating',
                        'comment',
                        'is_my_ratings',
                        'total_ratings_for_merchant',
                        'created_at',
                        'updated_at',
                    ],
                ],
            ]);

        foreach ($ratings as $rating) {
            $this->assertDatabaseHas('merchant_ratings', [
                'id' => $rating->id,
                'merchant_id' => $merchant->id,
                'user_id' => $rating->user_id,
                'rating' => $rating->rating,
                'comment' => $rating->comment,
            ]);
        }
    }

    public function testPostMerchantRatings()
    {
        $merchant = Merchant::factory()->create();
        $ratingCategories = RatingCategory::factory()->count(3)->create();

        $ratingCategoriesIds = $ratingCategories->pluck('id')->toArray();
        $ratingData = [
            'rating' => 4,
            'comment' => 'Great service!',
            'rating_category_ids' => implode(',', $ratingCategoriesIds),
        ];

        $response = $this->postJson("/api/v1/merchants/{$merchant->id}/ratings", $ratingData);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'merchant_id',
                    'user_id',
                    'user' => [
                        'id',
                        'name',
                        'username',
                        'avatar',
                        'avatar_thumb',
                    ],
                    'rating',
                    'comment',
                    'is_my_ratings',
                    'total_ratings_for_merchant',
                    'created_at',
                    'updated_at',
                ],
            ]);

        // assert that the rating was created
        $this->assertDatabaseHas('merchant_ratings', [
            'merchant_id' => $merchant->id,
            'user_id' => $this->user->id,
            'rating' => $ratingData['rating'],
            'comment' => $ratingData['comment'],
        ]);

        // assert rating categories attached to this rating_id
        foreach ($ratingCategories as $category) {
            $this->assertDatabaseHas('rating_categories_merchant_ratings', [
                'merchant_rating_id' => $response['data']['id'],
                'rating_category_id' => $category->id,
                'user_id' => $this->user->id,
            ]);
        }
    }

    public function testGetMerchantMenus()
    {
        $merchant = Merchant::factory()->create();

        $menus = [
            UploadedFile::fake()->create('menu1.pdf'),
            UploadedFile::fake()->create('menu2.pdf'),
            UploadedFile::fake()->create('menu3.pdf'),
        ];

        foreach ($menus as $menu) {
            $merchant->addMedia($menu)->toMediaCollection(Merchant::MEDIA_COLLECTION_MENUS);
        }

        $response = $this->getJson("/api/v1/merchants/{$merchant->id}/menus");

        $response->assertStatus(200);


        // ensure there's three urls returned
        $this->assertCount(3, $response->json());
    }

    public function testGetRatingCategories()
    {
        $ratingCategories = RatingCategory::factory()->count(5)->create();

        $response = $this->getJson('/api/v1/merchants/rating_categories');

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

    public function testGetArticlesOfMerchants()
    {
        // create a new user
        $user = User::factory()->create();
        // ensure countries and states are seeded first
        $this->seed(CountriesTableSeeder::class);
        $this->seed(StatesTableSeeder::class);

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

        // get ids of merchant using /merchants/{id}/locations
        $response = $this->getJson("/api/v1/merchants/{$merchant->id}/locations");
        $response->assertStatus(200);

        $location_ids = $response->json();
        // get articles with /api/v1/articles?location_id=1
        $response = $this->getJson('/api/v1/articles', ['location_id' => $location->id]);

        // make sure there's one article returned
        // 200 response
        $response->assertStatus(200);
        $this->assertCount(1, $response['data']);
    }
}
