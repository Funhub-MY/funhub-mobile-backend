<?php

namespace Tests\Unit;

use App\Models\PointLedger;
use App\Models\PointComponentLedger;
use App\Models\PromotionCode;
use App\Models\Reward;
use App\Models\RewardComponent;
use App\Models\User;
use App\Services\PointService;
use App\Services\PointComponentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PromotionCodeTest extends TestCase
{
    use RefreshDatabase;

    protected $user;
    protected $pointService;
    protected $pointComponentService;

    protected function setUp(): void
    {
        parent::setUp();

        // Create a user for all tests using factory
        $this->user = User::factory()->create();

        // Use real services instead of mocks
        $this->pointService = new PointService();
        $this->pointComponentService = new PointComponentService();

        $this->app->instance(PointService::class, $this->pointService);
        $this->app->instance(PointComponentService::class, $this->pointComponentService);

        // Authenticate user with all abilities
        Sanctum::actingAs($this->user, ['*']);
    }

    public function testRedeemPromotionCodeWithReward()
    {
        // Create a reward
        $reward = Reward::create([
            'name' => 'Test Reward',
            'description' => 'Test Description',
            'points' => 100,
            'user_id' => $this->user->id
        ]);

        // Create a promotion code with reward
        $promotionCode = PromotionCode::create([
            'code' => PromotionCode::generateUniqueCode(),
            'is_redeemed' => false,
        ]);
        $promotionCode->reward()->attach($reward->id, ['quantity' => 10]);

        // Test redeeming the code
        $response = $this->postJson('/api/v1/promotion-codes/redeem', [
            'code' => $promotionCode->code
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'message' => __('messages.success.promotion_code_controller.Code_redeemed_successfully')
            ]);

        // Assert the code is marked as redeemed
        $this->assertDatabaseHas('promotion_codes', [
            'id' => $promotionCode->id,
            'is_redeemed' => true,
            'claimed_by_id' => $this->user->id,
        ]);

        // Assert points were credited correctly (10 quantity * 100 points = 1000 points)
        $pointLedger = PointLedger::where('user_id', $this->user->id)
            ->where('pointable_type', PromotionCode::class)
            ->where('pointable_id', $promotionCode->id)
            ->first();

        $this->assertNotNull($pointLedger);
        $this->assertEquals(1000, $pointLedger->amount);
        $this->assertTrue((bool)$pointLedger->credit);
    }

    public function testRedeemPromotionCodeWithRewardComponent()
    {
        // Create a reward with components for 饭盒FUNHUB
        $reward = Reward::create([
            'name' => '饭盒FUNHUB',
            'description' => '饭盒FUNHUB',
            'points' => 1,
            'user_id' => $this->user->id
        ]);

        $components = [];
        $componentData = [
            ['name' => '鸡蛋', 'description' => '鸡蛋'],
            ['name' => '蔬菜', 'description' => '蔬菜'],
            ['name' => '饭', 'description' => '饭'],
            ['name' => '肉', 'description' => '肉'],
            ['name' => '盒子', 'description' => '盒子'],
        ];

        foreach ($componentData as $data) {
            $component = RewardComponent::create([
                'name' => $data['name'],
                'description' => $data['description'],
                'user_id' => $this->user->id,
            ]);
            $components[] = $component;
            $reward->rewardComponents()->attach($component->id, ['points' => 5]);
        }

        // Create a promotion code with reward components
        $promotionCode = PromotionCode::create([
            'code' => PromotionCode::generateUniqueCode(),
            'is_redeemed' => false,
        ]);

        // Attach all components to the promotion code
        foreach ($components as $component) {
            $promotionCode->rewardComponent()->attach($component->id, ['quantity' => 1]);
        }

        // Test redeeming the code
        $response = $this->postJson('/api/v1/promotion-codes/redeem', [
            'code' => $promotionCode->code
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'message' => __('messages.success.promotion_code_controller.Code_redeemed_successfully')
            ]);

        // Assert the code is marked as redeemed
        $this->assertDatabaseHas('promotion_codes', [
            'id' => $promotionCode->id,
            'is_redeemed' => true,
            'claimed_by_id' => $this->user->id,
        ]);

        // Assert component points were credited correctly for each component
        foreach ($components as $component) {
            $componentLedger = PointComponentLedger::where('user_id', $this->user->id)
                ->where('pointable_type', PromotionCode::class)
                ->where('pointable_id', $promotionCode->id)
                ->where('component_id', $component->id)
                ->first();

            $this->assertNotNull($componentLedger, "Component ledger for {$component->name} not found");
            $this->assertEquals(1, $componentLedger->amount);
            $this->assertTrue((bool)$componentLedger->credit);
        }
    }

    public function testCannotRedeemInvalidCode()
    {
        $response = $this->postJson('/api/v1/promotion-codes/redeem', [
            'code' => 'INVALID123'
        ]);

        $response->assertStatus(404)
            ->assertJson([
                'message' => __('messages.success.promotion_code_controller.Invalid_code')
            ]);
    }

    public function testCannotRedeemAlreadyRedeemedCode()
    {
        // Create a redeemed promotion code
        $promotionCode = PromotionCode::create([
            'code' => PromotionCode::generateUniqueCode(),
            'is_redeemed' => false,
            'claimed_by_id' => $this->user->id,
            'redeemed_at' => now(),
        ]);

        $response = $this->postJson('/api/v1/promotion-codes/redeem', [
            'code' => $promotionCode->code
        ]);

        $response->assertStatus(400)
            ->assertJson([
                'message' => __('messages.success.promotion_code_controller.Code_already_claimed')
            ]);
    }

    public function testCannotRedeemWhenPromotionGroupDisabled()
    {
        // Create a promotion code group with status disabled
        $group = new \App\Models\PromotionCodeGroup([
            'name' => 'Test Group',
            'description' => 'Test Description',
            'status' => false,
            'campaign_from' => now()->subDay(),
            'campaign_until' => now()->addDay(),
        ]);
        $group->save();

        // Create a promotion code
        $code = new PromotionCode([
            'code' => 'TEST123',
            'status' => true,
            'promotion_code_group_id' => $group->id
        ]);
        $code->save();

        // Try to redeem the code
        $response = $this->postJson('/api/v1/promotion-codes/redeem', [
            'code' => 'TEST123'
        ]);

        $response->assertStatus(400)
            ->assertJson(['message' => __('messages.success.promotion_code_controller.Campaign_disabled')]);
    }

    public function testCannotRedeemWhenCampaignNotStarted()
    {
        // Create a promotion code group with future start date
        $group = new \App\Models\PromotionCodeGroup([
            'name' => 'Test Group',
            'description' => 'Test Description',
            'status' => true,
            'campaign_from' => now()->addDay(),
            'campaign_until' => now()->addDays(2),
        ]);
        $group->save();

        // Create a promotion code
        $code = new PromotionCode([
            'code' => 'TEST123',
            'status' => true,
            'promotion_code_group_id' => $group->id
        ]);
        $code->save();

        // Try to redeem the code
        $response = $this->postJson('/api/v1/promotion-codes/redeem', [
            'code' => 'TEST123'
        ]);

        $response->assertStatus(400)
            ->assertJson(['message' => __('messages.success.promotion_code_controller.Campaign_not_started')]);
    }

    public function testCannotRedeemWhenCampaignEnded()
    {
        // Create a promotion code group with past end date
        $group = new \App\Models\PromotionCodeGroup([
            'name' => 'Test Group',
            'description' => 'Test Description',
            'status' => true,
            'campaign_from' => now()->subDays(2),
            'campaign_until' => now()->subDay(),
        ]);
        $group->save();

        // Create a promotion code
        $code = new PromotionCode([
            'code' => 'TEST123',
            'status' => true,
            'promotion_code_group_id' => $group->id
        ]);
        $code->save();

        // Try to redeem the code
        $response = $this->postJson('/api/v1/promotion-codes/redeem', [
            'code' => 'TEST123'
        ]);

        $response->assertStatus(400)
            ->assertJson(['message' => __('messages.success.promotion_code_controller.Campaign_ended')]);
    }

    public function testCanRedeemWithValidCampaign()
    {
        // Create a reward for the test
        $reward = Reward::create([
            'name' => 'Test Reward',
            'description' => 'Test Description',
            'points' => 100,
            'user_id' => $this->user->id
        ]);

        // Create a promotion code group with valid campaign period
        $group = new \App\Models\PromotionCodeGroup([
            'name' => 'Test Group',
            'description' => 'Test Description',
            'status' => true,
            'campaign_from' => now()->subDay(),
            'campaign_until' => now()->addDay(),
        ]);
        $group->save();

        // Create a promotion code
        $code = new PromotionCode([
            'code' => 'TEST123',
            'status' => true,
            'promotion_code_group_id' => $group->id
        ]);
        $code->save();

        // Attach reward to the code
        $code->reward()->attach($reward->id, ['quantity' => 1]);

        // Try to redeem the code
        $response = $this->postJson('/api/v1/promotion-codes/redeem', [
            'code' => 'TEST123'
        ]);

        $response->assertStatus(200)
            ->assertJson(['message' => __('messages.success.promotion_code_controller.Code_redeemed_successfully')]);

        // Check that points were credited
        $this->assertDatabaseHas('point_ledgers', [
            'user_id' => $this->user->id,
            'amount' => 100,
            'pointable_type' => PromotionCode::class,
            'pointable_id' => $code->id,
            'credit' => true
        ]);
    }
}
