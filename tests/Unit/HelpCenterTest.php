<?php

namespace Tests\Unit;

use App\Models\Article;
use App\Models\Faq;
use App\Models\User;
use App\Models\FaqCategory;
use Laravel\Sanctum\Sanctum;
use App\Models\SupportRequest;
use PHPUnit\Framework\TestCase;
use Illuminate\Http\UploadedFile;
use App\Models\SupportRequestMessage;
use App\Models\SupportRequestCategory;
use Database\Factories\SupportRequestFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create([]);

    $this->faqCategories = FaqCategory::factory()->count(2)->create([
        'user_id' => $this->user->id,
    ]);

    $this->faq1 = Faq::factory()->count(5)->create([
        'user_id' => $this->user->id,
        'faq_category_id' => $this->faqCategories->first()->id,
        'status' => 1,
    ]);

    $this->faq2 = Faq::factory()->count(5)->create([
        'user_id' => $this->user->id,
        'faq_category_id' => $this->faqCategories->get(1)->id,
        'status' => 1,
    ]);

    $this->supportRequestCategories = SupportRequestCategory::factory()->create();

    $this->article = Article::factory()->create();

    $this->supportRequest = SupportRequest::factory()->create([
        'requestor_id' => $this->user->id,
        'assignee_id' => $this->user->id,
        'category_id' => $this->supportRequestCategories->id,
    ]);

    $this->supportRequestMessage = SupportRequestMessage::factory()->create([
        'user_id' => $this->user->id,
        'support_request_id' => $this->supportRequest->id,
    ]);

    Sanctum::actingAs($this->user, ['*']);
});

it('testGetFaqsByCategories', function () {
    $category_id = $this->faqCategories->first()->id;
    $response = $this->getJson('api/v1/help/faqs?category_ids=' . $category_id);
    $response->assertStatus(200)
        ->assertJsonStructure([
            'data' => [
                '*' => [
                    'id',
                    'question',
                    'answer',
                    'category' => [
                        'id',
                        'name'
                    ],
                    'created_at',
                    'updated_at',
                ]
            ]
        ]);
    $this->assertEquals(5, $response->json('meta.total'));
    collect($response->json('data'))->each(function ($faq) use ($category_id) {
        $this->assertEquals($category_id, $faq['category']['id']);
    });
});

it('testGetAllFaqs', function () {
    $response = $this->getJson('api/v1/help/faqs');
    $response->assertStatus(200)
        ->assertJsonStructure([
            'data' => [
                '*' => [
                    'id',
                    'question',
                    'answer',
                    'category' => [
                        'id',
                        'name'
                    ],
                    'created_at',
                    'updated_at',
                ]
            ]
                ]);
    $this->assertEquals(10, $response->json('meta.total'));
});

it('testGetAllFaqsCategories', function () {
    $response = $this->getJson('api/v1/help/faq_categories');
    $response->assertStatus(200)
        ->assertJsonStructure([
            'data' => [
                '*' => [
                    'id',
                    'name',
                    'is_featured',
                    'created_at',
                    'updated_at',
                ]
            ]
        ])
        ->assertJsonCount(2, 'data');
});

it('testGetSupportRequests', function () {
    $response = $this->getJson('api/v1/help/support_requests');
    $response->assertStatus(200)
        ->assertJsonStructure([
            'data' => [
                '*' => [
                    'id',
                    'category',
                    'title',
                    'status',
                    'requestor',
                    'latest_message',
                    'assignee',
                    'created_at',
                    'updated_at',
                ]
            ]
        ]);
    $this->assertEquals(1, $response->json('meta.total'));
});

it('testRaiseSupportRequests', function () {
    $body = [];
    // test validation
    $response = $this->postJson('/api/v1/comments/report', $body);
    $response->assertStatus(422)
        ->assertJsonStructure([
            'message',
        ]);
    $body = [
        'category_id' => $this->supportRequestCategories->id,
        'title' => 'My support request',
        'message' => 'This is my message',
        'media_ids' => null
    ];
    // post another time.
    $response = $this->postJson('api/v1/help/support_requests/raise', $body);
    $response->assertStatus(200)
        ->assertJsonStructure([
            'message', 'request'
        ]);
    // check report.
    $this->assertDatabaseHas('support_requests', [
        'category_id' => $this->supportRequestCategories->id,
        'title' => 'My support request',
        'status' => 0,
    ]);
});

it('testReportArticleSupportRequest', function () {
    $body = [];
    // test validation
    $response = $this->postJson('/api/v1/comments/report', $body);
    $response->assertStatus(422)
        ->assertJsonStructure([
            'message',
        ]);
    $body = [
        'category_id' => $this->supportRequestCategories->id,
        'title' => 'Report Article',
        'message' => 'This is my message',
        'supportable' => 'article',
        'supportable_id' => $this->article->id,
        'media_ids' => null
    ];
    // post another time.
    $response = $this->postJson('api/v1/help/support_requests/raise', $body);
    $response->assertStatus(200)
        ->assertJsonStructure([
            'message', 'request'
        ]);
    // check report.
    $this->assertDatabaseHas('support_requests', [
        'category_id' => $this->supportRequestCategories->id,
        'title' => 'Report Article',
        'status' => 0,
    ]);
});

it('testReplySupportRequests', function () {
    $body = [];
    // test validation
    $response = $this->postJson("api/v1/help/support_requests/{$this->supportRequest->id}/reply", $body);
    $response->assertStatus(422)
        ->assertJsonStructure([
            'message'
        ]);
    $body = [
        'message' => 'This is my message',
        'media_ids' => null
    ];
    // post another time.
    $response = $this->postJson("api/v1/help/support_requests/{$this->supportRequest->id}/reply", $body);
    $response->assertStatus(200)
        ->assertJsonStructure([
            'message', 'request'
        ]);
    // check report.
    $this->assertDatabaseHas('support_requests_messages', [
        'user_id' => $this->user->id,
        'support_request_id' => $this->supportRequest->id,
        'message' => 'This is my message',
    ]);
});

it('testResolveSupportRequests', function () {
    $response = $this->postJson("api/v1/help/support_requests/{$this->supportRequest->id}/resolve");
    $response->assertStatus(200)
        ->assertJson([
            'message' => 'Support request resolved and closed',
        ]);
});

it('testUploadAttachmentForSupportRequests', function () {
    $body = [
        'images' => UploadedFile::fake()->image('test.jpg'),
    ];
    $response = $this->postJson('api/v1/help/support_requests/attach', $body);
    $this->assertTrue($this->user->media->where('file_name', 'test.jpg')->count() > 0);
    $response->assertStatus(200)
        ->assertJsonStructure([
            'uploaded' => [
                '*' => ['id', 'name', 'url', 'size', 'type']
            ]
        ]);
});
