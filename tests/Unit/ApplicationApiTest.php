<?php
namespace Tests\Unit;

use Tests\TestCase;
use App\Models\Store;
use App\Models\Location;
use App\Models\Application;
use App\Models\Country;
use App\Models\State;
use Database\Seeders\StatesTableSeeder;
use Database\Seeders\CountriesTableSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

class ApplicationApiTest extends TestCase
{
    use RefreshDatabase;

    protected $application;
    protected $token;
    protected $state;
    protected $country;
    protected function setUp(): void
    {
        parent::setUp();

        // create application and token
        $this->application = Application::create([
            'name' => 'Test Application',
            'description' => 'Test Application Description',
            'api_key' => \Illuminate\Support\Str::random(32),
            'status' => true
        ]);

        $this->token = $this->application->createToken(
            'Test Token',
        );

    }

    public function testExternalLocationEndpointRequiresValidToken()
    {
        // test without token
        $response = $this->getJson('/api/v1/external/locations');
        $response->assertStatus(401);

        // test with invalid token
        $response = $this->getJson('/api/v1/external/locations', [
            'Authorization' => 'Bearer invalid-token'
        ]);
        $response->assertStatus(401);
    }

    public function testExternalStoreEndpointRequiresValidToken()
    {
        // test without token
        $response = $this->getJson('/api/v1/external/stores');
        $response->assertStatus(401);

        // test with invalid token
        $response = $this->getJson('/api/v1/external/stores', [
            'Authorization' => 'Bearer invalid-token'
        ]);
        $response->assertStatus(401);
    }

    public function testInactiveApplicationCannotAccessApi()
    {
        // deactivate application
        $this->application->update(['status' => false]);

        $response = $this->getJson('/api/v1/external/locations', [
            'Authorization' => 'Bearer ' . $this->token->plainTextToken
        ]);

        $response->assertStatus(403)
            ->assertJson(['message' => 'Application is inactive']);
    }
}
