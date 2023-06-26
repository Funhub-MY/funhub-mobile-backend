<?php

namespace Tests\Unit;

use App\Models\Article;
use App\Models\Comment;
use App\Models\Mission;
use App\Models\Reward;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use App\Models\RewardComponent;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
    Sanctum::actingAs($this->user,['*']);

    // ensure rewards and reward components are created
    $reward = Reward::create([
        'name' => '饭盒FUNHUB',
        'description' => '饭盒FUNHUB',
        'points' => 1, // current 1 reward is 1 of value
        'user_id' => $this->user->id
    ]);

    if ($reward) {
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

            $reward->rewardComponents()->attach($rewardComponent->id, ['points' => 5]);
        }
    }
});

it('can create a mission', function () {
    // get reward component
    $component = RewardComponent::where('name', '鸡蛋')->first();

    $mission = Mission::factory()->create([
        'name' => 'Comment on 10 articles',
        'description' => 'Comment on 10 articles',
        'event' => 'comment_created',
        'value' => 10,
        'missionable_type' => RewardComponent::class,
        'missionable_id' => $component->id,
        'reward_quantity' => 1,
        'enabled' => 1,
        'status' => 1,
        'user_id' => $this->user->id
    ]);
    expect($mission->name)->toBe('Comment on 10 articles');
});

it('User can comment on 10 articles and get rewarded by mission', function () {
    $articles = Article::factory()->count(10)->create();

    // get reward component
    $component = RewardComponent::where('name', '鸡蛋')->first();

    // create a mission for article count to 10 and reward a component
    $mission = Mission::factory()->create([
        'name' => 'Comment on 10 articles',
        'description' => 'Comment on 10 articles',
        'event' => 'comment_created',
        'value' => 10,
        'missionable_type' => RewardComponent::class,
        'missionable_id' => $component->id,
        'reward_quantity' => 1,
        'enabled' => 1,
        'status' => 1,
        'user_id' => $this->user->id
    ]);

    foreach ($articles as $article) {
        $response = $this->postJson('/api/v1/comments', [
            'id' => $article->id,
            'type' => 'article',
            'body' => 'test comment',
            'parent_id' => null
        ]); 
    }

    // check user mission process current_value
    $userMission = $this->user->missionsParticipating()->where('mission_id', $mission->id)
        ->first();


    expect((int) $userMission->pivot->current_value)->toBe(10);

    // expect pivot is_completed is false
    expect($userMission->pivot->is_completed)->toBe(0);

    // user send request to complete mission
    $response = $this->postJson('/api/v1/missions/complete', ['mission_id' => $mission->id]);
    expect($response->status())->toBe(200);

    // expect pivot is_completed is true
    expect($this->user->missionsParticipating->first()->pivot->is_completed)
        ->toBe(1);

    // expect response do contain reward.object and quantity
    expect($response->json('reward.object.id'))->toBe($component->id);

    // expect response do contain reward_quantity
    expect($response->json('reward.quantity'))->toBe(1);

    // check if user have reward component credited
    $response = $this->getJson('/api/v1/points/my_balance/all');
        expect($response->status())->toBe(200);

    // expect response json have point_components with id $component->id
    expect($response->json('point_components.*.id'))
        ->toContain($component->id);

    // expect response json have point_components with balance of 1
    expect($response->json('point_components.*.balance'))
        ->toContain(1);
});

it('User can get missions, completed missions, participating missions and missions completed yet to claim', function() {
    // get reward component
    $component = RewardComponent::where('name', '鸡蛋')->first();

    // create a mission for article count to 10 and reward a component
    $mission = Mission::factory()->create([
        'name' => 'Comment on 10 articles',
        'description' => 'Comment on 10 articles',
        'event' => 'comment_created',
        'value' => 10,
        'missionable_type' => RewardComponent::class,
        'missionable_id' => $component->id,
        'reward_quantity' => 1,
        'enabled' => 1,
        'status' => 1,
        'user_id' => $this->user->id
    ]);

    $response = $this->getJson('/api/v1/missions');
        expect($response->status())->toBe(200);

    // expect response json have missions with id $mission->id
    expect($response->json('data.*.id'))
        ->toContain($mission->id);

    // start the mission by commenting
    $articles = Article::factory()->count(1)->create();
    $response = $this->postJson('/api/v1/comments', [
        'id' => $articles->first()->id,
        'type' => 'article',
        'body' => 'test comment',
        'parent_id' => null
    ]); 

    $response = $this->getJson('/api/v1/missions');
        expect($response->status())->toBe(200);

    // expect response json have missions with id $mission->id and participating = true
    expect($response->json('data.*.id'))
        ->toContain($mission->id);

    $articles = Article::factory()->count(9)->create();
    foreach ($articles as $article) {
        $response = $this->postJson('/api/v1/comments', [
            'id' => $article->id,
            'type' => 'article',
            'body' => 'test comment',
            'parent_id' => null
        ]); 
    }

    $response = $this->getJson('/api/v1/missions');
        expect($response->status())->toBe(200);
        
    // expect response json to have is_participating true, andd current_value = 10
    expect($response->json('data.*.is_participating'))
        ->toContain(true);

    expect($response->json('data.*.current_value'))
        ->toContain(10);

    // claim and query claimed_only
    $response = $this->postJson('/api/v1/missions/complete', ['mission_id' => $mission->id]);

    $response = $this->getJson('/api/v1/missions?claimed_only=true');
        expect($response->status())->toBe(200);

    expect($response->json('data.*.id'))
        ->toContain($mission->id);
});

