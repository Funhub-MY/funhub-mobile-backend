<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Models\Article;
use App\Models\ArticleCategory;
use App\Models\ArticleTag;
use App\Models\User;
use App\Models\View;
use Laravel\Sanctum\Sanctum;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;

class NotificationTest extends TestCase
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
     * Create three notifications and check user has unread or not
     */
    public function testNotifications() {
        // create an article with logged in user
        $article = Article::factory()->create([
            'user_id' => $this->user->id
        ]);

        // create three random users
        $users = User::factory()->count(3)->create();

        // each user interact "like" to article
        foreach ($users as $user) {
            $this->actingAs($user);

            // each like will fire a ArticleInteracted Notification
            $response = $this->postJson('/api/v1/interactions', [
                'id' => $article->id,
                'interactable' => 'article',
                'type' => 'like',
            ]);
        }
        
        // revert to actingAs logged in user
        $this->actingAs($this->user);

        // get notifications
        $response = $this->getJson('/api/v1/notifications');

        // check if response 200
        $response->assertStatus(200);
        // get total should be 3
        $this->assertEquals(3, $response->json('meta.total'));
        // check foreach data['from_id'] should be in $users
        foreach ($response->json('data') as $notification) {
            // assert notification has article_id
            $this->assertArrayHasKey('article_id', $notification);

            // assert article_id = $article->id
            $this->assertEquals($article->id, $notification['article_id']);

            $this->assertTrue(in_array($notification['from_user']['id'], $users->pluck('id')->toArray()));
        }

        // mark all as read
        $response = $this->postJson('/api/v1/notifications/mark_all_as_read');

        // check if response 200
        $response->assertStatus(200);

        // asset user unread notifications count is zero
        $this->assertEquals(0, $this->user->unreadNotifications()->count());


    }
}
