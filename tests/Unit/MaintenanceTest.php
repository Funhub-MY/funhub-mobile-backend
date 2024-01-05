<?php

namespace Tests\Unit;

use App\Models\User;
use App\Models\Maintenance;
use Laravel\Sanctum\Sanctum;
// use PHPUnit\Framework\TestCase;
use Tests\TestCase;
use Illuminate\Support\Facades\Log;
use Illuminate\Foundation\Testing\RefreshDatabase;

class MaintenanceTest extends TestCase
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
     * Test getMaintenanceInfo

     *
     * @return void
     */
    public function testGetMaintenanceInfo()
    {
        // Create a scheduled maintenance
        $maintenance = Maintenance::factory()->create();

        // Get maintenance info
        $response = $this->getJson('/api/v1/maintenance');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'data' => [
                'data'
            ],
        ]);

        $testData = $response->json('data')['data'][0];

        $this->assertArrayHasKey('start_date', $testData);
        $this->assertArrayHasKey('end_date', $testData);
        $this->assertArrayHasKey('is_active', $testData);
    }
}
