<?php
namespace Tests\Unit;

use Tests\TestCase;
use App\Models\MerchantOffer;
use App\Models\MerchantCategory;
use App\Models\User;
use Laravel\Sanctum\Sanctum;
use Illuminate\Foundation\Testing\RefreshDatabase;

class MerchantCategoryTest extends TestCase
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
     * Test Get Merchant Category
     * /api/v1/merchant_categories
     */
    public function testGetPopularCategories()
    {
        // create article categories first
        $merchantCategories = MerchantCategory::factory()->count(5)->create([
            'user_id' => $this->user->id
        ]);

        // get article categories
        $response = $this->getJson('/api/v1/merchant_categories');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data'
            ]);

        $this->assertEquals(5, $response->json('meta.total'));
    }
}
