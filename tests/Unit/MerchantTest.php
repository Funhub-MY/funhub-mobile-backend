<?php

namespace Tests\Unit;

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
}
