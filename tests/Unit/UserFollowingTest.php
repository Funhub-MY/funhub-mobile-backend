<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Models\User;
use App\Models\UserFollowing;
use App\Notifications\Newfollower;
use App\Notifications\Userfollowed;
use Laravel\Sanctum\Sanctum;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

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

        // make users follow $this->user
        foreach ($users as $user) {
            $this->actingAs($user);
            $response = $this->postJson('/api/v1/user/follow', [
                'user_id' => $this->user->id,
            ]);
        }

        // revert back to acting as this user
        $this->actingAs($this->user);

        $response = $this->getJson('/api/v1/user/followers');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data'
            ]);

        // assert should be 10 followers
        $this->assertCount(10, $response->json()['data']);

        // check if the followers are the same as the users
        $this->assertEquals(
            $users->pluck('id')->toArray(), 
            collect($response->json()['data'])->pluck('id')->toArray()
        );
    }

    /**
     * Test Get Followings Of Logged In User
     * /api/v1/user/followings
     */
    public function testGetFollowingsOfLoggedInUser()
    {
        // create 10 users for logged in user to follow
        $users = User::factory()->count(10)->create();

        // attach followings to $this->user
        foreach ($users as $user) {
            // acting as logged in user, follow each of the users created
            $response = $this->postJson('/api/v1/user/follow', [
                'user_id' => $user->id,
            ]);
        }

        $response = $this->getJson('/api/v1/user/followings');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data'
            ]);
        
        // assert should be 10 followings
        $this->assertCount(10, $response->json()['data']);

        // check if followings of logged in user id are same as the users id
        $this->assertEquals(
            $users->pluck('id')->toArray(), 
            collect($response->json()['data'])->pluck('id')->toArray()
        );

        // ensure json data each user is_following is true
        foreach ($response->json()['data'] as $user) {
            $this->assertTrue($user['is_following']);
        }
    }

    /**
     * Test Get Followers Of User Via User Id
     * /api/v1/user/followings?user_id={user_id}
     */
    public function testGetFollowerViaUserId()
    {
        $user = User::factory()->create();

        // logged in user follow $user
        $this->postJson('/api/v1/user/follow', [
            'user_id' => $user->id,
        ]);

        // get followers of $user
        $response = $this->getJson('/api/v1/user/followers?user_id=' . $user->id);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data'
            ]);

        // assert should be 1 follower
        $this->assertCount(1, $response->json()['data']);

        // assert the follower should be $this->user
        $this->assertEquals(
            $this->user->id, 
            collect($response->json()['data'])->pluck('id')->first()
        );
    }


    /**
     * Test Get Followings Of User Via User Id
     * /api/v1/user/followings?user_id={user_id}
     */
    public function testGetFollowingsViaUserId()
    {
        $user = User::factory()->create();

        // logged in user follow $user
        $this->postJson('/api/v1/user/follow', [
            'user_id' => $user->id,
        ]);

        // get followers of $user
        $response = $this->getJson('/api/v1/user/followings?user_id=' . $this->user->id);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data'
            ]);

        // assert should be 1 follower
        $this->assertCount(1, $response->json()['data']);

        // assert the follower should be $this->user
        $this->assertEquals(
            $user->id, 
            collect($response->json()['data'])->pluck('id')->first()
        );
    }

    /**
     * Test Following Notification
     */
    public function testFollowingNotification()
    {
        Notification::fake();

        $user = User::factory()->create();

        // logged in user follow $user
        $this->postJson('/api/v1/user/follow', [
            'user_id' => $user->id,
        ]);

        Notification::assertSentTo(
            [$user], Newfollower::class,
            function ($notification, $channels) {
                return in_array('database', $channels);
            }
        );
    }
}
