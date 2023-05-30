<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Models\Article;
use App\Models\Interaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use App\Models\User;
use App\Notifications\ArticleInteracted;
use Illuminate\Support\Facades\Notification;

class InteractionTest extends TestCase
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
     * Test get interactions of a interactable (article)
     * /api/v1/interactions
     */
    public function testGetInteractions()
    {
        // create new article
        $article = Article::factory()->create();

        // create interactions of like
        $interactions = Interaction::factory()->count(5)->create([
            'interactable_id' => $article->id,
            'interactable_type' => Article::class,
            'type' => Interaction::TYPE_LIKE,
            'user_id' => $this->user->id,
        ]);

        // get interactions
        $response = $this->getJson('/api/v1/interactions?id='.$article->id.'&interactable='.Article::class);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data'
            ]);

        $this->assertEquals(5, count($response->json('data')));
    }

    /**
     * Test get interactions of a interactable (article) without interactable
     * /api/v1/interactions
     */
    public function testGetInteractionsWithoutInteractable()
    {
        // create new article
        $article = Article::factory()->create();

        // create interactions of like
        $interactions = Interaction::factory()->count(5)->create([
            'interactable_id' => $article->id,
            'interactable_type' => Article::class,
            'type' => Interaction::TYPE_LIKE,
            'user_id' => $this->user->id,
        ]);

        // get interactions
        $response = $this->getJson('/api/v1/interactions?id='.$article->id);

        $response->assertStatus(422)
            ->assertJsonStructure([
                'errors' => [
                    'interactable'
                ]
            ]);
    }

    /**
     * Test get interactions of a interactable (article)
     * /api/v1/interactions
     */
    public function testCreateInteraction()
    {
        // create new article
        $article = Article::factory()->create();

        // create interaction
        $response = $this->postJson('/api/v1/interactions', [
            'id' => $article->id,
            'interactable' => 'article',
            'type' => 'like',
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'interaction'
            ]);

        $this->assertDatabaseHas('interactions', [
            'interactable_id' => $article->id,
            'interactable_type' => Article::class,
            'type' => Interaction::TYPE_LIKE,
            'user_id' => $this->user->id,
        ]);
    }

    /**
     * Test get interactions of a interactable (article)
     * /api/v1/interactions/{id}
     */
    public function testGetOneInteraction()
    {
        // create new article
        $article = Article::factory()->create();

        // create interaction
        $interaction = Interaction::factory()->create([
            'interactable_id' => $article->id,
            'interactable_type' => Article::class,
            'type' => Interaction::TYPE_LIKE,
            'user_id' => $this->user->id,
        ]);

        // get interaction
        $response = $this->getJson('/api/v1/interactions/'.$interaction->id);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'interaction'
            ]);
    }

    /**
     * Test delete interaction
     * /api/v1/interactions
     */
    public function testDeleteInteraction()
    {
        // create new article
        $article = Article::factory()->create();

        // create interaction
        $interaction = Interaction::factory()->create([
            'interactable_id' => $article->id,
            'interactable_type' => Article::class,
            'type' => Interaction::TYPE_LIKE,
            'user_id' => $this->user->id,
        ]);

        // delete interaction
        $response = $this->deleteJson('/api/v1/interactions/'.$interaction->id);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'message'
            ]);

        $this->assertDatabaseMissing('interactions', [
            'id' => $interaction->id,
        ]);
    }

    /**
     * Test Share Interaction
     * /api/v1/interactions
     */
    public function testShareInteraction()
    {
        // create new article
        $article = Article::factory()->create();

        // create interaction
        $response = $this->postJson('/api/v1/interactions', [
            'id' => $article->id,
            'interactable' => Article::class,
            'type' => 'share',
        ]);

        // find Shareable Link generated
        $interaction = Interaction::where('interactable_id', $article->id)
            ->where('interactable_type', Article::class)
            ->where('type', Interaction::TYPE_SHARE)
            ->where('user_id', $this->user->id)
            ->first();

        $response->assertStatus(200)
            ->assertJsonStructure([
                'interaction' => [
                    'share_url'
                ]
            ]);

        // ensure json share_url value is url('/s/{link}')
        $this->assertEquals(url('/s/'.$interaction->shareableLink->first()->link), $response->json('interaction.share_url'));

        $this->assertDatabaseHas('interactions', [
            'interactable_id' => $article->id,
            'interactable_type' => Article::class,
            'type' => Interaction::TYPE_SHARE,
            'user_id' => $this->user->id,
        ]);

        // check shareable link table
        $this->assertDatabaseHas('shareable_links', [
            'model_id' => $article->id,
            'model_type' => Article::class,
            'link' => $interaction->shareableLink->first()->link,
            'user_id' => $this->user->id,
        ]);
    }

    /**
     * Test Interaction Notification
     */
    public function testInteractionNotification()
    {
        Notification::fake();

        // create a fake user
        $user = User::factory()->create();

        // create new article
        $article = Article::factory()->create([
            'user_id' => $user->id,
        ]);

        // create interaction by logged in user on $user's article
        $response = $this->postJson('/api/v1/interactions', [
            'id' => $article->id,
            'interactable' => 'article',
            'type' => 'like',
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'interaction'
            ]);

        // check notification
        Notification::assertSentTo(
            [$user], ArticleInteracted::class
        );
    }
}
