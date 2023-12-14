<?php

namespace Tests\Unit;

use App\Models\Campaign;
use Tests\TestCase;

use App\Models\User;
use Laravel\Sanctum\Sanctum;
use Illuminate\Foundation\Testing\RefreshDatabase;

class CampaignTest extends TestCase
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

    public function testGetActiveCampaigns()
    {
        // create one active, one inactive campaign
        $activeCampaign = Campaign::factory()->create([
            'is_active' => true
        ]);

        $inactiveCampaign = Campaign::factory()->create([
            'is_active' => false
        ]);

        // get active campaigns
        $response = $this->getJson('/api/v1/campaigns/active');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'has_active_campaign',
                'campaigns'
            ]);

        // count json campaigns is one
        $this->assertCount(1, $response->json('campaigns'));
    }


}
