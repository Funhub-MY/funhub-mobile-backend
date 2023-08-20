<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Models\Article;
use App\Models\Comment;
use App\Models\Interaction;
use App\Models\User;
use App\Models\UserBlock;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

class UserDeletionTest extends TestCase
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

    public function testDeleteUserAfterUserActivities()
    {
        // create articles for this user
        $articles = Article::factory()->count(10)->published()->create([
            'user_id' => $this->user->id
        ]);

        // 1 comments on each articles by user
        foreach ($articles as $article) {
            $response = $this->postJson('/api/v1/comments', [
                'id' => $article->id,
                'type' => 'article',
                'body' => 'test comment',
                'parent_id' => null
            ]);
        }

        // like each articles
        foreach ($articles as $article) {
            $response = $this->postJson('/api/v1/interactions', [
                'id' => $article->id,
                'interactable' => 'article',
                'type' => 'like',
            ]);
        }

        // delete account
        $response = $this->postJson('/api/v1/user/delete', [
            'reason' => 'bye'
        ]);

        $response->assertStatus(200);
        // assert message correct
        $response->assertJson([
            'message' => 'Account deleted successfully.'
        ]);

        // assert user is has been status marked as archived
        $this->assertDatabaseHas('users', [
            'id' => $this->user->id,
            'status' => User::STATUS_ARCHIVED
        ]);

        // assert articles by user has been archived
        $this->assertDatabaseHas('articles', [
            'user_id' => $this->user->id,
            'status' => Article::STATUS_ARCHIVED
        ]);

        // check user account deletion has record for this user
        $this->assertDatabaseHas('user_account_deletions', [
            'user_id' => $this->user->id,
            'reason' => 'bye'
        ]);
    }
}
