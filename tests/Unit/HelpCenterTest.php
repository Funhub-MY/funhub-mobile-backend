<?php

namespace Tests\Unit;

use App\Models\Faq;
use App\Models\User;
use App\Models\FaqCategory;
use Laravel\Sanctum\Sanctum;
use App\Models\SupportRequest;
use PHPUnit\Framework\TestCase;
use App\Models\SupportRequestMessage;
use App\Models\SupportRequestCategory;
use Database\Factories\SupportRequestFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create([
        'id' => 1,
    ]);

    $this->faq = Faq::factory()->create([
        'user_id' => $this->user->id,
        'faq_category_id' => 1,
    ]);

    $this->faqCatogories = FaqCategory::factory()->create([
        'user_id' => $this->user->id,
    ]);

    $this->supportRequestCategories = SupportRequestCategory::factory()->create();

    $this->supportRequest = SupportRequest::factory()->create([
        'requestor_id' => $this->user->id,
        'assignee_id' => $this->user->id,
        'category_id' => $this->supportRequestCategories->id,
    ]);

    $this->supportRequestMessage = SupportRequestMessage::factory()->create([
        'user_id' => $this->user->id,
        'support_request_id' => $this->supportRequest->id,
    ]);

    Sanctum::actingAs($this->user,['*']);
});

it('testGetAllFaqs', function () {
    $response = $this->getJson('api/v1/help/faqs');
    $response->assertStatus(200)
        ->assertJsonStructure([
            'data'
        ]);
});

it('testGetAllFaqsCategories', function () {
    $response = $this->getJson('api/v1/help/faq_categories');
    $response->assertStatus(200)
        ->assertJsonStructure([
            'data'
        ]);
});

it('testGetSupportRequests', function () {
    $response = $this->getJson('api/v1/help/support_requests');
    $response->assertStatus(200)
        ->assertJsonStructure([
            'data'
        ]);
});

it('testRaiseSupportRequests', function () {
    $body = [];
    // test validation
    $response = $this->postJson('/api/v1/comments/report', $body);
    $response->assertStatus(422)
        ->assertJsonStructure([
            'message', 
        ]);
    $body =[
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

it('testReplySupportRequests', function () {
    $body = [];
    // test validation
    $response = $this->postJson("api/v1/help/support_requests/{$this->supportRequest->id}/reply", $body);
    $response->assertStatus(422)
        ->assertJsonStructure([
            'message'
        ]);
    $body =[
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

