<?php

namespace Tests\Unit;

use App\Models\MerchantOfferCategory;
use Tests\TestCase;
use App\Models\User;
use Laravel\Sanctum\Sanctum;
use Illuminate\Foundation\Testing\RefreshDatabase;

class MerchantOfferCategoryTest extends TestCase
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
     * Test Get Merchant Offer Category
     * /api/v1/merchant_offer_categories
     */
    public function testGetAllActiveMerchantOfferCategories()
    {
        // Create merchant offer categories first
        $offerCategories = MerchantOfferCategory::factory()->count(5)->create();

        // get merchant offer categories (default query is_active is true)
        $response = $this->getJson('/api/v1/merchant_offer_categories');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data'
            ]);

        $this->assertEquals(5, $response->json('meta.total'));
    }

    public function testGetMerchantOfferCategorieswithParam()
    {
        // Create merchant offer categories with different characteristics where
        // First 5 categories is_featured true, is_active true
        // Next 5 categories is_featured false, is_active false, parent_id is 1
        $offerCategories = MerchantOfferCategory::factory()->count(10)->create();

        // Update the characteristics for the first 5 categories
        $firstFiveCategories = $offerCategories->take(5);
        $firstFiveCategories->each(function ($category, $index) {
            $category->update([
                'is_featured' => true,
                'is_active' => true,
            ]);
        });

        // Update the characteristics for the next 5 categories
        $nextFiveCategories = $offerCategories->skip(5)->take(5);
        $nextFiveCategories->each(function ($category, $index) {
            $category->update([
                'is_featured' => false,
                'is_active' => false,
                'parent_id' => 1,
            ]);
        });

        // 1. get merchant offer categories where is_featured is true (default query is_active is true)
        $response = $this->getJson('/api/v1/merchant_offer_categories?is_featured=1');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data'
            ]);

        $this->assertEquals(5, $response->json('meta.total'));

        // 2. get merchant offer categories where is_featured is true and is_active is false
        $response = $this->getJson('/api/v1/merchant_offer_categories?is_featured=1&is_active=0');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data'
            ]);

        $this->assertEquals(0, $response->json('meta.total'));

        // 3. get merchant offer categories where is_featured is false, is_active is false, and parent id=1
        $response = $this->getJson('/api/v1/merchant_offer_categories?is_featured=0&is_active=0&id=1');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data'
            ]);

        $this->assertEquals(5, $response->json('meta.total'));

        // 4. get merchant offer categories where parent id=1 (default query is_active is true)
        $response = $this->getJson('/api/v1/merchant_offer_categories?id=1');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data'
            ]);

        $this->assertEquals(0, $response->json('meta.total'));

        // 5. get merchant offer categories where is_active is false and parent id=1
        $response = $this->getJson('/api/v1/merchant_offer_categories?id=1&is_active=0');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data'
            ]);

        $this->assertEquals(5, $response->json('meta.total'));
    }
}
