<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Models\Article;
use App\Models\User;
use App\Models\UserBlock;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

class UserBlockTest extends TestCase
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

    public function testBlockAUser()
    {
        $userToBlock = User::factory()->create();

        $response = $this->postJson('/api/v1/user/block', [
            'user_id' => $userToBlock->id,
            'reason' => 'spam'
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'message'
            ]);

        // assert block message 'User blocked'
        $this->assertEquals('User blocked', $response['message']);
        // assert db user_blocks has record
        $this->assertDatabaseHas('user_blocks', [
            'user_id' => $this->user->id,
            'blockable_id' => $userToBlock->id,
            'blockable_type' => 'App\Models\User',
            'reason' => 'spam'
        ]);
    }

    public function testGetMyBlockedUsers()
    {
        // create 10 users to block
        $usersToBlock = User::factory()->count(10)->create();

        // block these users
        foreach ($usersToBlock as $userToBlock) {
            $response = $this->postJson('/api/v1/user/block', [
                'user_id' => $userToBlock->id,
                'reason' => 'spam'
            ]);
            $response->assertStatus(200)
            ->assertJsonStructure([
                'message'
            ]);
        }

        // get my blocked users
        $response = $this->getJson('/api/v1/user/my_blocked_users');
        $response->assertStatus(200)
            ->assertJsonStructure([
                'data'
            ]);

        // check each json data has blockable_id = usersToBlock ids
        foreach ($response['data'] as $blockedUser) {
            $this->assertContains($blockedUser['id'], $usersToBlock->pluck('id'));
        }
    }

    public function testBlockedUserShouldNotBeAbleSeeMyArticlesProfiles()
    {
        // create ten articles by a user
        $user = User::factory()->create();
        $unBlockedUser = User::factory()->create();
        $articles = Article::factory()->count(10)->published()->create([
            'user_id' => $user->id
        ]);

        $unblockedArticles = Article::factory()->count(10)->published()->create([
            'user_id' => $unBlockedUser->id
        ]);

        // i block this user
        $response = $this->postJson('/api/v1/user/block', [
            'user_id' => $user->id,
            'reason' => 'spam'
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure(['message']);

        // ensure db has block user->id
        $this->assertDatabaseHas('user_blocks', [
            'user_id' => $this->user->id,
            'blockable_id' => $user->id,
            'blockable_type' => 'App\Models\User',
            'reason' => 'spam'
        ]);

        // i should not be able to see this user articles
        $response = $this->getJson('/api/v1/articles');
        $response->assertStatus(200)
            ->assertJsonStructure([
                'data'
            ]);

        // returned total should be 10 of the unblocked articles
        $this->assertEquals(10, $response->json('meta.total'));

        // loop each data to ensure the ids are from unblockedArticles
        foreach ($response->json('data') as $article) {
            $this->assertContains($article['id'], $unblockedArticles->pluck('id'));
        }

        // go to blocked user's profile using /user/{user} should be 404
        $response = $this->getJson('/api/v1/user/' . $user->id);
        $response->assertStatus(404);

        // vice versa, if my blocked user tris to go to my profile, should be 404
        // acting as $user
        Sanctum::actingAs($user,['*']);
        $response = $this->getJson('/api/v1/user/' . $this->user->id);
        $response->assertStatus(404);
    }

    public function testUnblockAUser()
    {
        // create a user to block
        $userToBlock = User::factory()->create();
        // block user
        $response = $this->postJson('/api/v1/user/block', [
            'user_id' => $userToBlock->id,
            'reason' => 'spam'
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'message'
            ]);

        // assert db has this block record
        $this->assertDatabaseHas('user_blocks', [
            'user_id' => $this->user->id,
            'blockable_id' => $userToBlock->id,
            'blockable_type' => 'App\Models\User',
            'reason' => 'spam'
        ]);

        // unblock user
        $response = $this->postJson('/api/v1/user/unblock', [
            'user_id' => $userToBlock->id
        ]);
        // assert status 200 and has message
        $response->assertStatus(200)
            ->assertJsonStructure([
                'message'
            ]);

        $this->user->refresh();

        // check this->user->userBlocks has no record
        $this->assertNull($this->user->usersBlocked()->first());
    }

    public function testUserBlockAndUnfollow()
    {
        // create another user
        $userToBlock = User::factory()->create();

        // follow each other
        $response = $this->postJson('/api/v1/user/follow', [
            'user_id' => $userToBlock->id,
        ]);
        $response->assertStatus(200)
            ->assertJsonStructure([
                'message'
            ]);

        // act as $user
        Sanctum::actingAs($userToBlock,['*']);
        // follow $this->user
        $response = $this->postJson('/api/v1/user/follow', [
            'user_id' => $this->user->id,
        ]);
        $response->assertStatus(200)
            ->assertJsonStructure([
                'message'
            ]);

        // act as $this->user
        Sanctum::actingAs($this->user,['*']);
        // block $userToBlock
        $response = $this->postJson('/api/v1/user/block', [
            'user_id' => $userToBlock->id,
            'reason' => 'spam'
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'message'
            ]);

        // ensure user followers dont have $userToBlock
        $response = $this->getJson('/api/v1/user/followers');
        $response->assertStatus(200)
            ->assertJsonStructure([
                'data'
            ]);
        foreach ($response->json('data') as $follower) {
            $this->assertNotEquals($follower['id'], $userToBlock->id);
        }

        // vice versa, ensure userToBlock followers dont have $this->user
        // act as $userToBlock
        Sanctum::actingAs($userToBlock,['*']);
        $response = $this->getJson('/api/v1/user/followers');
        $response->assertStatus(200)
            ->assertJsonStructure([
                'data'
            ]);
        foreach ($response->json('data') as $follower) {
            $this->assertNotEquals($follower['id'], $this->user->id);
        }
    }
}
