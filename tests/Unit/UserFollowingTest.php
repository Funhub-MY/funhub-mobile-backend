<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Models\User;
use Laravel\Sanctum\Sanctum;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;

/**
 * Test User Following
 */
class UserFollowingTest extends TestCase
{
    use RefreshDatabase;
    protected $user;

    protected function setUp(): void
    {
        parent::setUp();

        // reset database
        $this->refreshDatabase();

        // mock log in user get token
        $this->user = User::factory()->create();
        Sanctum::actingAs($this->user,['*']);
    }

    /**
     * Test Follow A User By Logged In User
     * /api/v1/user/follow
     */
    public function testFollowAUserByLoggedInUser()
    {
        $user = User::factory()->create();
        $response = $this->postJson('/api/v1/user/follow', [
            'user_id' => $user->id,
        ]);
        $response->assertStatus(200)
            ->assertJsonStructure([
                'message'
            ]);

        $this->assertDatabaseHas('users_followings', [
            'user_id' => $this->user->id,
            'following_id' => $user->id,
        ]);
    }

    /**
     * Test Unfollow A User By Logged In User
     * /api/v1/user/unfollow
     */
    public function testUnfollowAUserByLoggedInUser()
    {
        $user = User::factory()->create();

        // create following of user first
        $this->postJson('/api/v1/user/follow', [
            'user_id' => $user->id,
        ]);

        // then unfollow it
        $response = $this->postJson('/api/v1/user/unfollow', [
            'user_id' => $user->id,
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'message'
            ]);

        $this->assertDatabaseMissing('users_followings', [
            'user_id' => $this->user->id,
            'following_id' => $user->id,
        ]);
    }

    /**
     * Test Get Followers Of Logged In User
     * /api/v1/user/followers
     */
    public function testGetFollowersOfLoggedInUser()
    {
        $users = User::factory()->count(10)->create();

        // attach followers to $this->user
        $this->user->followers()->attach($users);

        $response = $this->getJson('/api/v1/user/followers');

        Log::info($response->json());

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data'
            ]);
    }

    /**
     * Test Get Followings Of Logged In User
     * /api/v1/user/followings
     */
    public function testGetFollowingsOfLoggedInUser()
    {
        $users = User::factory()->count(10)->create();

        // attach followings to $this->user
        $this->user->followings()->attach($users);

        $response = $this->getJson('/api/v1/user/followings');


        $response->assertStatus(200)
            ->assertJsonStructure([
                'data'
            ]);
    }
}
