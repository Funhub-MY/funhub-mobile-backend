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

    /**
     * Test block a user
     *
     * @return void
     */
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

    /**
     * Test Get a list of my blocked users
     *
     * @return void
     */
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

    /**
     * Test blocked users should not be able to see my articles or profile
     *
     * @return void
     */
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

    /**
     * Test Unblock a User that I have blocked
     *
     * @return void
     */
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

    /**
     * Test user block and should auto unfollow each other and not appear on each other's followers list
     *
     * @return void
     */
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

    /**
     * Test block user and should not see each other comment on article but third party can see all
     *
     * @return void
     */
    public function testBlockUserAndShouldNotSeeEachOtherCommentOnArticle()
    {
        // user to block
        $userToBlock = User::factory()->create();

        // third party user
        $thirdPartyUser = User::factory()->create();

        // create an article by third party user
        $article = Article::factory()->published()->create([
            'user_id' => $thirdPartyUser->id
        ]);

        // act as thirdparty user comment on this article
        Sanctum::actingAs($thirdPartyUser,['*']);
        $response = $this->postJson('/api/v1/comments', [
            'id' => $article->id,
            'type' => 'article',
            'body' => 'test comment',
            'parent_id' => null
        ]);
        $response->assertStatus(200)->assertJsonStructure(['comment']);

        // comment on article by me
        Sanctum::actingAs($this->user,['*']);
        $response = $this->postJson('/api/v1/comments', [
            'id' => $article->id,
            'type' => 'article',
            'body' => 'test comment',
            'parent_id' => null
        ]);
        $response->assertStatus(200)->assertJsonStructure(['comment']);

        // acting as user to block, comment on same article
        Sanctum::actingAs($userToBlock,['*']);
        $response = $this->postJson('/api/v1/comments', [
            'id' => $article->id,
            'type' => 'article',
            'body' => 'test comment',
            'parent_id' => null
        ]);
        $response->assertStatus(200)->assertJsonStructure(['comment']);

        // I will now block userToBlock
        Sanctum::actingAs($this->user,['*']);
        $response = $this->postJson('/api/v1/user/block', [
            'user_id' => $userToBlock->id,
            'reason' => 'spam'
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'message'
            ]);

        // now get comments for this article i should not see any comments
        $response = $this->getJson('/api/v1/comments?type=article&id=' . $article->id);
        $response->assertStatus(200)
            ->assertJsonStructure([
                'data'
            ]);

        // get all data.user.id out as array
        $dataUserIds = collect($response->json('data'))->pluck('user.id')->toArray();

        // loop each json data ensure no comments by userToBlock
        foreach ($response->json('data') as $comment) {
            $this->assertNotEquals($comment['user']['id'], $userToBlock->id);
        }

        $this->assertEquals(2, $response->json('meta.total')); // can see own and third party

        // act as userToBlock perform same steps, should not see any comments made by $this->user
        Sanctum::actingAs($userToBlock,['*']);
        $response = $this->getJson('/api/v1/comments?type=article&id=' . $article->id);
        $response->assertStatus(200)
            ->assertJsonStructure([
                'data'
            ]);

        // loop each json data ensure no comments by $this->user
        foreach ($response->json('data') as $comment) {
            $this->assertNotEquals($comment['user']['id'], $this->user->id);
        }

        // can see own and third party
        $this->assertEquals(2, $response->json('meta.total'));

        // act as third party to get comments for article
        Sanctum::actingAs($thirdPartyUser,['*']);
        $response = $this->getJson('/api/v1/comments?type=article&id=' . $article->id);
        $response->assertStatus(200)
            ->assertJsonStructure([
                'data'
            ]);

        // loop each json data ensure all comments are there
        foreach ($response->json('data') as $comment) {
            $this->assertContains($comment['user']['id'], [$this->user->id, $userToBlock->id, $thirdPartyUser->id]);
        }

        // ensure meta.total is 3
        $this->assertEquals(3, $response->json('meta.total'));
    }

    /**
     * Test block user and should not see each other in article tagged users
     *
     * @return void
     */
    public function testBlockUserAndShouldNotSeeEachOtherInArticleTag()
    {
        // create a user to block
        $userToBlock = User::factory()->create();
        // create third party user
        $thirdPartyUser = User::factory()->create();

        // acting as third party user create an article with 3 users tagged
        Sanctum::actingAs($thirdPartyUser,['*']);
        $article = Article::factory()->published()->create([
            'user_id' => $thirdPartyUser->id,
        ]);

        $article->taggedUsers()->attach([$this->user->id, $userToBlock->id, $thirdPartyUser->id]);

        // acting as $this->user block $userToBlock
        Sanctum::actingAs($this->user,['*']);
        $response = $this->postJson('/api/v1/user/block', [
            'user_id' => $userToBlock->id,
            'reason' => 'spam'
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'message'
            ]);

        // get article tagged users
        $response = $this->getJson('/api/v1/articles/tagged_users?article_id=' . $article->id);
        $response->assertStatus(200)
            ->assertJsonStructure([
                'data'
            ]);

        // ensure $userToBlock is not in the list
        foreach ($response->json('data') as $taggedUser) {
            $this->assertNotEquals($taggedUser['id'], $userToBlock->id);
        }

        // ensure meta.total is 2
        // act as userToBlock to get tagged users
        Sanctum::actingAs($userToBlock,['*']);
        $response = $this->getJson('/api/v1/articles/tagged_users?article_id=' . $article->id);
        $response->assertStatus(200)
            ->assertJsonStructure([
                'data'
            ]);

        // ensure $this->user is not in the list
        foreach ($response->json('data') as $taggedUser) {
            $this->assertNotEquals($taggedUser['id'], $this->user->id);
        }

        // third party should see all 3
        Sanctum::actingAs($thirdPartyUser,['*']);
        $response = $this->getJson('/api/v1/articles/tagged_users?article_id=' . $article->id);
        $response->assertStatus(200)
            ->assertJsonStructure([
                'data'
            ]);

        // ensure meta.total is 3
        $this->assertEquals(3, $response->json('meta.total'));

        // ensure all 3 users are in the list
        foreach ($response->json('data') as $taggedUser) {
            $this->assertContains($taggedUser['id'], [$this->user->id, $userToBlock->id, $thirdPartyUser->id]);
        }
    }

}
