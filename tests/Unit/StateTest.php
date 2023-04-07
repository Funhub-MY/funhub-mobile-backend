<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Models\User;
use Laravel\Sanctum\Sanctum;
use Illuminate\Foundation\Testing\RefreshDatabase;

class StateTest extends TestCase
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
     * Test Get States by Logged In User
     * /api/v1/states
     */
    public function testGetStatesByLoggedInUser()
    {
        // seed country first
        $this->seed('CountriesTableSeeder');
        
        // seed state
        $this->seed('StatesTableSeeder');

        $response = $this->getJson('/api/v1/states');
        $response->assertStatus(200)
            ->assertJsonStructure([
                '*' => [
                    'id',
                    'name',
                    'code',
                    'country_id'
                ]
            ]);
    }
}
