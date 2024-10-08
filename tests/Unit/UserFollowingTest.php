<?php

namespace Tests\Unit;

use App\Models\FollowRequest;
use Tests\TestCase;
use App\Models\User;
use App\Models\UserFollowing;
use App\Notifications\Newfollower;
use App\Notifications\Userfollowed;
use Laravel\Sanctum\Sanctum;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
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
        $this->assertEqualsCanonicalizing(
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

        // get my followers with name
        $response = $this->getJson('/api/v1/user/followers?user_id=' . $user->id . '&query=' . $this->user->name);
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

        // get my followers with name
        $response = $this->getJson('/api/v1/user/followings?user_id=' . $this->user->id . '&query=' . $user->name);
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

    public function testUnfollowDetachTaggedInArticles()
    {
        $user = User::factory()->create();

        // $this->user follows this $user first
        $this->postJson('/api/v1/user/follow', [
            'user_id' => $user->id,
        ]);

        // create article and tag $this->user in it
         // create article with users tagged
         $this->actingAs($user);
         // upload images first
         $response = $this->json('POST', '/api/v1/articles/gallery', [
            'images' => UploadedFile::fake()->image('test.jpg')
        ]);
        // create article category factory
        $categories = \App\Models\ArticleCategory::factory()
            ->count(2)
            ->create();

        // get ids array out of response json uploaded
        $image_ids = array_column($response->json('uploaded'), 'id');

        $response = $this->postJson('/api/v1/articles', [
            'title' => 'Test Article with Images',
            'body' => 'Test Article Body',
            'type' => 'multimedia',
            'published_at' => now(),
            'status' => 1,
            'published_at' => now()->toDateTimeString(),
            'tags' => ['#test', '#test2'],
            'categories' => $categories->pluck('id')->toArray(),
            'images' => $image_ids,
            'tagged_user_ids' => [$this->user->id],
        ]);
        $articleId = $response->json('article.id');

        // back to acting $this->user
        $this->actingAs($this->user);

        // $this->user nfollows $user
        $response = $this->postJson('/api/v1/user/unfollow', [
            'user_id' => $user->id,
        ]);

        // check if see article taggedUsers do not have $this->user
        $this->assertDatabaseMissing('articles_tagged_users', [
            'article_id' => $articleId,
            'user_id' => $this->user->id,
        ]);
    }

    public function testFollowAPrivateProfileUser()
    {
        // create a new user
        $user = User::factory()->create();

        // make user profile private
        // change to private
        $this->actingAs($user);
        $response = $this->postJson('/api/v1/user/settings/profile-privacy', [
            'profile_privacy' => 'private',
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'profile_privacy' => 'private',
        ]);

        // change back to current user
        $this->actingAs($this->user);

        // follow the private profile user
        $response = $this->postJson('/api/v1/user/follow', [
            'user_id' => $user->id,
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'message'
            ]);

        // message should be requested
        $response->assertJson([
            'message' => 'Follow request sent',
        ]);

        // check if follow request is sent in db
        $this->assertDatabaseHas('follow_requests', [
            'user_id' => $this->user->id,
            'following_id' => $user->id,
            'accepted' => false
        ]);

        $this->actingAs($user); // acting as the user who is being followed
        // get my following requests
        $response = $this->getJson('/api/v1/user/request_follows');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data'
            ]);

        // $user accept the follow request
        $response = $this->postJson('/api/v1/user/request_follow/accept', [
            'user_id' => $this->user->id,
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'message'
            ]);

        // check if follow request is accepted in db
        $this->assertDatabaseHas('follow_requests', [
            'user_id' => $this->user->id,
            'following_id' => $user->id,
            'accepted' => true
        ]);

        // check user followings table
        $this->assertDatabaseHas('users_followings', [
            'user_id' => $this->user->id,
            'following_id' => $user->id
        ]);
    }

    public function testRejectAFollowRequest() {
        // create a new user
        $user = User::factory()->create();

        // make user profile private
        // change to private
        $this->actingAs($user);
        $response = $this->postJson('/api/v1/user/settings/profile-privacy', [
            'profile_privacy' => 'private',
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'profile_privacy' => 'private',
        ]);

        // change back to current user
        $this->actingAs($this->user);

        // follow the private profile user
        $response = $this->postJson('/api/v1/user/follow', [
            'user_id' => $user->id,
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'message'
            ]);

        // message should be requested
        $response->assertJson([
            'message' => 'Follow request sent',
        ]);

        // check if follow request is sent in db
        $this->assertDatabaseHas('follow_requests', [
            'user_id' => $this->user->id,
            'following_id' => $user->id,
            'accepted' => false
        ]);

        $this->actingAs($user); // acting as the user who is being followed
        // get my following requests
        $response = $this->getJson('/api/v1/user/request_follows');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data'
            ]);

        // $user reject the follow request
        $response = $this->postJson('/api/v1/user/request_follow/reject', [
            'user_id' => $this->user->id,
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'message'
            ]);

        // check if follow request is rejected in db
        $this->assertDatabaseMissing('follow_requests', [
            'user_id' => $this->user->id,
            'following_id' => $user->id,
            'accepted' => true
        ]);

        // check user followings table
        $this->assertDatabaseMissing('users_followings', [
            'user_id' => $this->user->id,
            'following_id' => $user->id
        ]);

    }
}
