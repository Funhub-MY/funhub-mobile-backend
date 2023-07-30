<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Models\Article;
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

class LocationTest extends TestCase
{
    use RefreshDatabase;
    protected $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->refreshDatabase();

        // seed
        $this->seed(CountriesTableSeeder::class);
        $this->seed(StatesTableSeeder::class);

        // mock log in user get token
        $this->user = User::factory()->create();
        Sanctum::actingAs($this->user,['*']);
    }

    /**
     * @test Test Load Locations List
     */
    public function testGetLocationsList()
    {
        // mock create locations
        $locations = \App\Models\Location::factory()->count(5)->create();

        $response = $this->getJson('/api/v1/locations');
        $response->assertStatus(200);

        // check if 5 json.data returned
        $response->assertJsonCount(5, 'data');

        // check if each json.data elements contains $location->id
        collect($response->json('data'))->each(function ($location) use ($locations) {
            $this->assertContains($location['id'], $locations->pluck('id'));
        });
    }

    public function testGetSingleLocation()
    {
        // mock create location
        $location = \App\Models\Location::factory()->create();

        $response = $this->getJson('/api/v1/locations/' . $location->id);
        $response->assertStatus(200);

        // check if json.data contains $location->id
        $response->assertJsonFragment(['id' => $location->id]);
    }
}