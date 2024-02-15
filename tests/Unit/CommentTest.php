<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Models\Article;
use App\Models\Comment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use App\Models\User;
use App\Notifications\Commented;
use App\Notifications\TaggedUserInComment;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

class CommentTest extends TestCase
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
     * Test Create Comment
     * /api/v1/comments
     */
    public function testCreateComment()
    {
        // create new article
        $article = Article::factory()->create();

        // create comment
        $response = $this->postJson('/api/v1/comments', [
            'id' => $article->id,
            'type' => 'article',
            'body' => 'test comment',
            'parent_id' => null
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'comment'
            ]);

        $this->assertDatabaseHas('comments', [
            'body' => 'test comment',
            'commentable_id' => $article->id,
            'commentable_type' => Article::class,
            'user_id' => $this->user->id,
        ]);
    }

    /**
     * Test Create Comment Without Body
     * /api/v1/comments
     */
    public function testUpdateAComment()
    {
        // create new article
        $article = Article::factory()->create();

        // create comment
        $comment = Comment::factory()->create([
            'commentable_id' => $article->id,
            'commentable_type' => Article::class,
            'user_id' => $this->user->id,
        ]);

        // update comment
        $response = $this->putJson('/api/v1/comments/'.$comment->id, [
            'body' => 'test updated comment'
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'message'
            ]);

        $this->assertDatabaseHas('comments', [
            'body' => 'test updated comment',
            'commentable_id' => $article->id,
            'commentable_type' => Article::class,
            'user_id' => $this->user->id,
        ]);
    }

    /**
     * Test Reply To Comment
     * /api/v1/comments
     */
    public function testReplyToComment()
    {
        // create new article
        $article = Article::factory()->create();
        $comment = Comment::factory()->create([
            'commentable_id' => $article->id,
            'commentable_type' => Article::class,
            'user_id' => $this->user->id,
        ]);

        // create a reply
        $response = $this->postJson('/api/v1/comments', [
            'id' => $article->id,
            'type' => 'article',
            'parent_id' => $comment->id,
            'body' => 'test reply to comment'
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'comment'
            ]);

        $this->assertDatabaseHas('comments', [
            'body' => 'test reply to comment',
            'commentable_id' => $article->id,
            'commentable_type' => Article::class,
            'user_id' => $this->user->id,
            'parent_id' => $comment->id,
        ]);
    }

    /**
     * Test Get Comments of a Commentable Type
     * /api/v1/comments
     */
    public function testGetCommentsOfAType()
    {
        // create new article
        $article = Article::factory()->create();
        $otherArticle = Article::factory()->create();

        $comments = Comment::factory()->count(10)->create([
            'commentable_id' => $article->id,
            'commentable_type' => Article::class,
            'user_id' => $this->user->id,
        ]);

        // create subsequent unrelated comments on another article
        Comment::factory()->count(10)->create([
            'commentable_id' => $otherArticle->id,
            'commentable_type' => Article::class,
            'user_id' => $this->user->id,
        ]);

        // add comments to this article
        $article->comments()->saveMany($comments);

        // create a reply
        $response = $this->getJson('/api/v1/comments?id='.$article->id.'&type=article');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data'
            ]);

        // ensure each data.commentable_id is $article->id
        foreach ($response->json('data') as $comment) {
            $this->assertEquals($article->id, $comment['commentable_id']);
        }
    }

    /**
     * Test Get Comments of a Commentable Type
     * /api/v1/comments
     */
    public function testDeleteCommentByLoggedInUser()
    {
        // create new article
        $article = Article::factory()->create();
        $comment = Comment::factory()->create([
            'commentable_id' => $article->id,
            'commentable_type' => Article::class,
            'user_id' => $this->user->id,
        ]);

        // delete comment
        $response = $this->deleteJson('/api/v1/comments/'.$comment->id);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'message'
            ]);

        $this->assertDatabaseMissing('comments', [
            'id' => $comment->id,
        ]);
    }

    /**
     * Test Get Comments of a Commentable Type
     * /api/v1/comments
     */
    public function testCommentLikeToggle()
    {
        // create new article
        $article = Article::factory()->create();
        $comment = Comment::factory()->create([
            'commentable_id' => $article->id,
            'commentable_type' => Article::class,
            'user_id' => $this->user->id,
        ]);

        // like comment
        $response = $this->postJson('/api/v1/comments/like_toggle', [
            'comment_id' => $comment->id,
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'message'
            ]);

        $this->assertDatabaseHas('comments_likes', [
            'comment_id' => $comment->id,
            'user_id' => $this->user->id,
        ]);

        // get comment by id and asset json structure counts.likes is 1
        $response = $this->getJson('/api/v1/comments/'.$comment->id);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'comment'
            ]);


        $this->assertEquals(1, $response->json('comment.counts.likes'));

        // unlike comment
        $response = $this->postJson('/api/v1/comments/like_toggle', [
            'comment_id' => $comment->id,
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'message'
            ]);

        $this->assertDatabaseMissing('comments_likes', [
            'comment_id' => $comment->id,
            'user_id' => $this->user->id,
        ]);

         // get comment by id and asset json structure counts.likes is 0
         $response = $this->getJson('/api/v1/comments/'.$comment->id);

         $response->assertStatus(200)
             ->assertJsonStructure([
                 'comment'
             ]);

         $this->assertEquals(0, $response->json('comment.counts.likes'));
    }

    /**
     * Test Delete a comment where its liked
     *
     * /api/v1/comments
     */
    public function testLikedCommentAndDeleteComment()
    {
        // create new article
        $article = Article::factory()->create();
        $comment = Comment::factory()->create([
            'commentable_id' => $article->id,
            'commentable_type' => Article::class,
            'user_id' => $this->user->id,
        ]);

        // like comment
        $response = $this->postJson('/api/v1/comments/like_toggle', [
            'comment_id' => $comment->id,
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'message'
            ]);

        $this->assertDatabaseHas('comments_likes', [
            'comment_id' => $comment->id,
            'user_id' => $this->user->id,
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'message'
            ]);

        // delete comment
        $response = $this->deleteJson('/api/v1/comments/'.$comment->id);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'message'
            ]);

        $this->assertDatabaseMissing('comments', [
            'id' => $comment->id,
        ]);

        $this->assertDatabaseMissing('comments_likes', [
            'comment_id' => $comment->id,
            'user_id' => $this->user->id,
        ]);
    }

    /**
     * Test Comment Notification
     */
    public function testCommentNotification()
    {
        Notification::fake();

        // create a fake user
        $user = User::factory()->create();

        // create new article
        $article = Article::factory()->create([
            'user_id' => $user->id,
        ]);

        // create a reply with logged in user
        $response = $this->postJson('/api/v1/comments', [
            'id' => $article->id,
            'type' => 'article',
            'body' => 'test reply to comment'
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'comment'
            ]);

        Notification::assertSentTo(
            [$user], Commented::class,
            function ($notification, $channels) {
                return in_array('database', $channels);
            }
        );
    }

    public function testTagFollowerInComment()
    {
        Notification::fake();

        // create a fake user
        $userA = User::factory()->create();
        $userB = User::factory()->create();

        // follow logged in user
        $this->actingAs($userA);
         // $this->user follows this $user first
         $this->postJson('/api/v1/user/follow', [
            'user_id' => $this->user->id,
        ]);

        // act as default user again
        $this->actingAs($this->user);

        // create a new artcile
        $article = Article::factory()->create([
            'user_id' => $this->user->id,
        ]);

        // get taggable users
        $response = $this->getJson('/api/v1/comments/taggable_users');
        $response->assertStatus(200);

        $this->assertEquals(1, count($response->json('data')));

        // total count of pagination is 1
        $this->assertEquals(1, $response->json('meta.total'));

        // data first object id is userA id
        $this->assertEquals($userA->id, $response->json('data.0.id'));

        // create a comment tagging user A  (as logged in user)
        $response = $this->postJson('/api/v1/comments', [
            'id' => $article->id,
            'type' => 'article',
            'body' => 'test reply to comment',
            'tagged_users' => [$userA->id]
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'comment'
            ]);

        // check database comments_users has record
        $this->assertDatabaseHas('comments_users', [
            'comment_id' => $response->json('comment.id'),
            'user_id' => $userA->id,
        ]);

        // check repsonse comment->tagged_users has userA
        $this->assertEquals($userA->id, $response->json('comment.tagged_users.0.id'));

        // assert notification sent to userA
        Notification::assertSentTo(
            [$userA], TaggedUserInComment::class,
            function ($notification, $channels) {
                return in_array('database', $channels);
            }
        );
    }
}
