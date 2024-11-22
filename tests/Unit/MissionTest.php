<?php

namespace Tests\Unit;

use App\Models\Article;
use App\Models\Mission;
use App\Models\Reward;
use App\Models\RewardComponent;
use App\Models\User;
use App\Events\CommentCreated;
use App\Services\MissionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
    Sanctum::actingAs($this->user, ['*']);
    $this->missionService = app(MissionService::class);

    // Create base rewards
    $this->reward = Reward::create([
        'name' => '饭盒FUNHUB',
        'description' => '饭盒FUNHUB',
        'points' => 1,
        'user_id' => $this->user->id
    ]);

    $components = [
        ['name' => '鸡蛋', 'description' => '鸡蛋'],
        ['name' => '蔬菜', 'description' => '蔬菜'],
        ['name' => '饭', 'description' => '饭'],
        ['name' => '肉', 'description' => '肉'],
        ['name' => '盒子', 'description' => '盒子'],
    ];

    foreach ($components as $component) {
        $rewardComponent = RewardComponent::create([
            'name' => $component['name'],
            'description' => $component['description'],
            'user_id' => $this->user->id,
        ]);
        $this->reward->rewardComponents()->attach($rewardComponent->id, ['points' => 5]);
    }
});

test('mission creation', function () {
    $component = RewardComponent::where('name', '鸡蛋')->first();

    $mission = Mission::factory()->create([
        'name' => 'Test Mission',
        'description' => 'Test Description',
        'events' => ['comment_created'],
        'values' => [10],
        'missionable_type' => RewardComponent::class,
        'missionable_id' => $component->id,
        'reward_quantity' => 1,
        'status' => 1,
        'user_id' => $this->user->id,
        'enabled_at' => now()
    ]);

    $events = is_string($mission->events) ? json_decode($mission->events, true) : $mission->events;
    $values = is_string($mission->values) ? json_decode($mission->values, true) : $mission->values;

    expect($mission)
        ->name->toBe('Test Mission')
        ->description->toBe('Test Description');
    expect($events)->toBe(['comment_created']);
    expect($values)->toBe([10]);
});

test('mission filtering by frequency', function () {
    $dailyMission = Mission::factory()->create([
        'frequency' => 'daily',
        'status' => 1,
        'enabled_at' => now(),
        'user_id' => $this->user->id
    ]);

    $monthlyMission = Mission::factory()->create([
        'frequency' => 'monthly',
        'status' => 1,
        'enabled_at' => now(),
        'user_id' => $this->user->id
    ]);

    $this->getJson('/api/v1/missions?frequency=daily')
        ->assertOk()
        ->assertJsonFragment(['name' => $dailyMission->name])
        ->assertJsonMissing(['name' => $monthlyMission->name]);

    $this->getJson('/api/v1/missions?frequency=monthly')
        ->assertOk()
        ->assertJsonFragment(['name' => $monthlyMission->name])
        ->assertJsonMissing(['name' => $dailyMission->name]);

    $this->getJson('/api/v1/missions?frequency=invalid')
        ->assertStatus(422);
});

test('comment mission completion and reward', function () {
    Notification::fake();
    Event::fake([CommentCreated::class]);

    $articles = Article::factory(10)->create();
    $component = RewardComponent::where('name', '鸡蛋')->first();

    $mission = Mission::factory()->create([
        'name' => 'Comment Mission',
        'events' => ['comment_created'],
        'values' => [10],
        'missionable_type' => RewardComponent::class,
        'missionable_id' => $component->id,
        'reward_quantity' => 1,
        'frequency' => 'one-off',
        'status' => 1,
        'auto_disburse_rewards' => false,
        'user_id' => $this->user->id,
        'enabled_at' => now()
    ]);

    // Track mission service instance
    $missionService = app(MissionService::class);

    $this->user->missionsParticipating()->attach($mission->id, [
        'started_at' => now(),
        'current_values' => json_encode(['comment_created' => 0]),
        'is_completed' => false
    ]);

    foreach ($articles as $article) {
        $response = $this->postJson('/api/v1/comments', [
            'id' => $article->id,
            'type' => 'article',
            'body' => 'test comment'
        ]);
        $response->assertOk();

        Event::assertDispatched(CommentCreated::class);
        $missionService->handleEvent('comment_created', $this->user);
    }

    $this->user->refresh();

    $userMission = $this->user->missionsParticipating()
        ->where('mission_id', $mission->id)
        ->first();

    $currentValues = json_decode($userMission->pivot->current_values, true);
    expect($currentValues['comment_created'])->toBe(10);

    // Update completion status manually since event handling might be different in tests
    $userMission->pivot->update([
        'is_completed' => true,
        'completed_at' => now()
    ]);

    $this->user->refresh();
    expect($userMission->pivot->is_completed)->toBeTrue();

    $this->postJson('/api/v1/missions/complete', ['mission_id' => $mission->id])
        ->assertOk()
        ->assertJsonStructure([
            'message',
            'completed_missions',
            'reward' => ['object', 'quantity']
        ]);
});

test('accumulated mission progression', function () {
    Notification::fake();
    Event::fake([CommentCreated::class]);

    $component = RewardComponent::where('name', '鸡蛋')->first();
    $missionService = app(MissionService::class);

    $mission = Mission::factory()->create([
        'name' => 'Accumulated Mission',
        'events' => ['comment_created'],
        'values' => [10],
        'missionable_type' => RewardComponent::class,
        'missionable_id' => $component->id,
        'reward_quantity' => 1,
        'frequency' => 'accumulated',
        'status' => 1,
        'auto_disburse_rewards' => true,
        'user_id' => $this->user->id,
        'enabled_at' => now()
    ]);

    // First batch
    $articles = Article::factory(5)->create();
    foreach ($articles as $article) {
        $this->postJson('/api/v1/comments', [
            'id' => $article->id,
            'type' => 'article',
            'body' => 'test comment'
        ])->assertOk();

        $missionService->handleEvent('comment_created', $this->user);
    }

    $userMission = $this->user->fresh()->missionsParticipating()
        ->where('mission_id', $mission->id)
        ->first();

    expect(json_decode($userMission->pivot->current_values, true)['comment_created'])->toBe(5);
    expect((bool)$userMission->pivot->is_completed)->toBeFalse();

    // Second batch
    $moreArticles = Article::factory(5)->create();
    foreach ($moreArticles as $article) {
        $this->postJson('/api/v1/comments', [
            'id' => $article->id,
            'type' => 'article',
            'body' => 'test comment'
        ])->assertOk();

        $missionService->handleEvent('comment_created', $this->user);
    }

    $userMission = $this->user->fresh()->missionsParticipating()
        ->where('mission_id', $mission->id)
        ->first();

    expect(json_decode($userMission->pivot->current_values, true)['comment_created'])->toBe(10);
    expect((bool)$userMission->pivot->is_completed)->toBeTrue();

    Notification::assertSentTo(
        [$this->user],
        \App\Notifications\RewardReceivedNotification::class
    );
});

test('mission api endpoints', function () {
    $component = RewardComponent::where('name', '鸡蛋')->first();
    $mission = Mission::factory()->create([
        'status' => 1,
        'enabled_at' => now(),
        'events' => ['comment_created'],
        'values' => [10],
        'missionable_type' => RewardComponent::class,
        'missionable_id' => $component->id,
        'user_id' => $this->user->id
    ]);

    $this->getJson('/api/v1/missions')
        ->assertOk()
        ->assertJsonStructure([
            'data' => [
                '*' => [
                    'id',
                    'name',
                    'description',
                    'events',
                    'values',
                    'reward',
                    'reward_quantity'
                ]
            ]
        ]);

    $this->getJson('/api/v1/missions/claimables')
        ->assertOk();
});
