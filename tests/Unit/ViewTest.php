<?php

use Tests\TestCase;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

class ViewTest extends TestCase
{
    use RefreshDatabase;
    protected $user;

    public function setUp(): void
    {
        parent::setUp();

        // reset database
        $this->refreshDatabase();

        // mock log in user get token
        $this->user = User::factory()->create();
        Sanctum::actingAs($this->user,['*']);
    }

    public function testPostView()
    {
        $data = [
            'viewable_type' => 'article',
            'viewable_id' => 1,
        ];

        $response = $this->postJson('/api/v1/views', $data);

        $response
            ->assertOk()
            ->assertJson(['message' => 'View recorded']);
    }

    public function testGetViews()
    {
        $type = 'article';
        $id = 1;

        $response = $this->getJson("/api/v1/views/{$type}/{$id}");

        $response
            ->assertOk()
            ->assertJsonStructure([
                'views',
                'total',
            ]);
    }
}


