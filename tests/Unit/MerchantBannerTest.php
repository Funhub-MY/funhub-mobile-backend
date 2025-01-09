<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Models\MerchantBanner;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('public');
    $this->withoutExceptionHandling();
});

it('can get published banners', function () {
    // Create a published banner
    $publishedBanner = MerchantBanner::factory()->create([
        'status' => MerchantBanner::STATUS_PUBLISHED,
        'title' => 'Test Published Banner',
        'link_to' => 'https://example.com'
    ]);

    // Add mock banner image
    $file = UploadedFile::fake()->image('banner.jpg', 1200, 400);
    $publishedBanner->addMedia($file)
        ->preservingOriginal()
        ->toMediaCollection(MerchantBanner::MEDIA_COLLECTION_NAME, 'public');

    // Create a draft banner (should not appear in response)
    $draftBanner = MerchantBanner::factory()->create([
        'status' => MerchantBanner::STATUS_DRAFT,
        'title' => 'Test Draft Banner'
    ]);

    // Test the API endpoint
    $response = $this->getJson('/api/v1/merchant_banners');

    expect($response->status())->toBe(200);
    expect($response->json('data'))->toHaveCount(1);
    expect($response->json('data.0.title'))->toBe('Test Published Banner');
    expect($response->json('data.0.link_to'))->toBe('https://example.com');
    expect($response->json('data.0.id'))->toBe($publishedBanner->id);
    expect($response->json('data.0.banner_url'))->not->toBeNull();
});
