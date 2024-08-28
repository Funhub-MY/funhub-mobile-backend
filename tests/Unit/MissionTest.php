<?php

namespace Tests\Unit;

use App\Events\CompletedProfile;
use App\Events\UserSettingsUpdated;
use App\Models\Article;
use App\Models\ArticleCategory;
use App\Models\Comment;
use App\Models\Interaction;
use App\Models\Merchant;
use App\Models\MerchantOffer;
use App\Models\Mission;
use App\Models\PointLedger;
use App\Models\Reward;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use App\Models\RewardComponent;
use App\Models\Store;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
    Sanctum::actingAs($this->user, ['*']);

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
        'events' => json_encode(['comment_created']),
        'values' => json_encode(['comment_created' => 10]),
        'missionable_type' => RewardComponent::class,
        'missionable_id' => $component->id,
        'reward_quantity' => 1,
        'status' => 1,
        'user_id' => $this->user->id
    ]);
    expect($mission->name)->toBe('Comment on 10 articles');
});

// test filter missions by frequency daily or monthly
it('can filter missions by frequency daily or monthly', function () {
    // Create a daily mission
    $dailyMission = Mission::factory()->create([
        'name' => 'Daily Mission',
        'enabled_at' => now(),
        'frequency' => 'daily',
        'status' => 1,
        'user_id' => $this->user->id
    ]);

    // Create a monthly mission
    $monthlyMission = Mission::factory()->create([
        'name' => 'Monthly Mission',
        'enabled_at' => now(),
        'frequency' => 'monthly',
        'status' => 1,
        'user_id' => $this->user->id
    ]);

    // Test filtering by daily frequency
    $response = $this->getJson('/api/v1/missions?frequency=daily');
    $response->assertStatus(200);
    $response->assertJsonFragment(['name' => 'Daily Mission']);
    $response->assertJsonMissing(['name' => 'Monthly Mission']);

    // Test filtering by monthly frequency
    $response = $this->getJson('/api/v1/missions?frequency=monthly');
    $response->assertStatus(200);
    $response->assertJsonFragment(['name' => 'Monthly Mission']);
    $response->assertJsonMissing(['name' => 'Daily Mission']);

    // Test with an invalid frequency
    $response = $this->getJson('/api/v1/missions?frequency=invalid');
    $response->assertStatus(422);
});

it('User can comment on 10 articles and get rewarded by mission', function () {
    $articles = Article::factory()->count(10)->create();

    // get reward component
    $component = RewardComponent::where('name', '鸡蛋')->first();

    // create a mission for article count to 10 and reward a component
    $mission = Mission::factory()->create([
        'name' => 'Comment on 10 articles',
        'enabled_at' => now(),
        'description' => 'Comment on 10 articles',
        'events' => json_encode(['comment_created']),
        'values' => json_encode([10]),
        'missionable_type' => RewardComponent::class,
        'missionable_id' => $component->id,
        'reward_quantity' => 1,
        'frequency' => 'one-off',
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

    // check user mission process current_values
    $userMission = $this->user->missionsParticipating()->where('mission_id', $mission->id)
        ->first();

    // if current values is string, then decode first
    if (is_string($userMission->pivot->current_values)) {
        $userMission->pivot->current_values = json_decode($userMission->pivot->current_values, true);
    }

    expect(array_values($userMission->pivot->current_values)[0])->toBe(10);

    // since commented on 10 articles
    expect($userMission->pivot->is_completed)->toBe(1);

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

    // // expect response json have point_components with id $component->id
    // expect($response->json('point_components.*.id'))
    //     ->toContain($component->id);

    // // expect response json have point_components with balance of 1
    // expect($response->json('point_components.*.balance'))
    //     ->toContain(1);
});

it('User can get missions, completed missions, participating missions and missions completed yet to claim', function() {
    // get reward component
    $component = RewardComponent::where('name', '鸡蛋')->first();

    // create a mission for article count to 10 and reward a component
    $mission = Mission::factory()->create([
        'name' => 'Comment on 10 articles',
        'enabled_at' => now(),
        'description' => 'Comment on 10 articles',
        'events' => json_encode(['comment_created']),
        'values' => json_encode([10]),
        'missionable_type' => RewardComponent::class,
        'missionable_id' => $component->id,
        'reward_quantity' => 1,
        'frequency' => 'one-off',
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

    $userMission = $this->user->missionsParticipating()->where('mission_id', $mission->id)
        ->first();
    if (is_string($userMission->pivot->current_values)) {
        $userMission->pivot->current_values = json_decode($userMission->pivot->current_values, true);
    }
    expect(array_values($userMission->pivot->current_values)[0])->toBe(10);

    // claim and query claimed_only
    $response = $this->postJson('/api/v1/missions/complete', ['mission_id' => $mission->id]);

    $response = $this->getJson('/api/v1/missions?completed_only=1');
    expect($response->status())->toBe(200);

    expect($response->json('data.*.id'))
        ->toContain($mission->id);
});

it('User can get latest claimable missions', function () {
    $component = RewardComponent::where('name', '鸡蛋')->first();

    // create a mission for article count to 10 and reward a component
    $mission = Mission::factory()->create([
        'name' => 'Comment on 1 article',
        'description' => 'Comment on 1 article',
        'enabled_at' => now(),
        'events' => json_encode(['comment_created']),
        'values' => json_encode(['comment_created' => 1]),
        'missionable_type' => RewardComponent::class,
        'missionable_id' => $component->id,
        'reward_quantity' => 1,
        'frequency' => 'one-off',
        'status' => 1,
        'user_id' => $this->user->id
    ]);

    // start the mission by commenting
    $articles = Article::factory()->count(1)->create();
    $response = $this->postJson('/api/v1/comments', [
        'id' => $articles->first()->id,
        'type' => 'article',
        'body' => 'test comment',
        'parent_id' => null
    ]);

    $response = $this->getJson('/api/v1/missions/claimables');
    expect($response->status())->toBe(200);

    // expect response json have missions with id $mission->id
    expect($response->json('data.*.id'))
        ->toContain($mission->id);
});

// test like_article mission
it('User can like an article and get rewarded by mission', function () {
    Notification::fake();

    $articles = Article::factory()->count(10)->create();

    // get reward component
    $component = RewardComponent::where('name', '鸡蛋')->first();

    // create a mission for article count to 10 and reward a component
    $mission = Mission::factory()->create([
        'name' => 'Like 10 articles',
        'enabled_at' => now(),
        'description' => 'Like 10 articles',
        'events' => json_encode(['like_article']),
        'values' => json_encode([10]),
        'missionable_type' => RewardComponent::class,
        'missionable_id' => $component->id,
        'reward_quantity' => 1,
        'reward_limit' => 100,
        'frequency' => 'one-off',
        'status' => 1,
        'auto_disburse_rewards' => 1, // make sure set auto disburse on if want to check rewards immediately
        'user_id' => $this->user->id
    ]);

    foreach ($articles as $article) {
        // like each articles
        $response = $this->postJson('/api/v1/interactions', [
            'id' => $article->id,
            'interactable' => 'article',
            'type' => 'like',
        ]);

        // assert status is 200
        $response->assertStatus(200);
    }

    // check user mission process current_values
    $userMission = $this->user->missionsParticipating()->where('mission_id', $mission->id)
        ->first();

    // if current values is string, then decode first
    if (is_string($userMission->pivot->current_values)) {
        $userMission->pivot->current_values = json_decode($userMission->pivot->current_values, true);
    }

    // expect current_values to be 10
    expect(array_values($userMission->pivot->current_values)[0])->toBe(10);

    // expect pivot is_completed is true
    expect($this->user->missionsParticipating->first()->pivot->is_completed)
        ->toBe(1);

    // check notification fired
    Notification::assertSentTo(
        [$this->user],
        \App\Notifications\RewardReceivedNotification::class
    );

    // check user has received reward component
    $response = $this->getJson('/api/v1/points/components/balance?type=鸡蛋');
    expect($response->status())->toBe(200);

    // eg response
    // [
    //     'type' => $request->type,
    //     'balance' => $latestLedger->balance
    // ]

    // check if response has 鸡蛋 => 1
    expect($response->json('balance'))->toBe(1);

    /// spam 10 more likes to get another reward, should not get rewarded
    // create 10 more articles besides the 10 created above
    $articles = Article::factory()->count(10)->create();

    foreach ($articles as $article) {
        // like each articles
        $response = $this->postJson('/api/v1/interactions', [
            'id' => $article->id,
            'interactable' => 'article',
            'type' => 'like',
        ]);

        // assert status is 200
        $response->assertStatus(200);
    }

    // check user mission process current_values
    $userMission = $this->user->missionsParticipating()->where('mission_id', $mission->id)
        ->first();

    // if current values is string, then decode first
    if (is_string($userMission->pivot->current_values)) {
        $userMission->pivot->current_values = json_decode($userMission->pivot->current_values, true);
    }

    // expect current_values to be 10
    expect(array_values($userMission->pivot->current_values)[0])->toBe(10); // maintain at 10 because is_completed is true

    // check if balance egg still 1
    $response = $this->getJson('/api/v1/points/components/balance?type=鸡蛋');
    expect($response->status())->toBe(200);

    // check if response has 鸡蛋 => 1
    expect($response->json('balance'))->toBe(1);
});

// test completed profile mission
it('User can complete profile setup and get rewarded by mission', function () {

    // get reward component
    $component = RewardComponent::where('name', '鸡蛋')->first();

    // create a mission for article count to 10 and reward a component
    $mission = Mission::factory()->create([
        'name' => 'Complete profile',
        'enabled_at' => now(),
        'description' => 'Complete profile',
        'events' => json_encode(['completed_profile_setup']),
        'values' => json_encode([1]),
        'missionable_type' => RewardComponent::class,
        'missionable_id' => $component->id,
        'reward_quantity' => 1,
        'reward_limit' => 100,
        'frequency' => 'one-off',
        'status' => 1,
        'auto_disburse_rewards' => 1, // make sure set auto disburse on if want to check rewards immediately
        'user_id' => $this->user->id
    ]);

    // complete profile by user
    $response = $this->postJson('/api/v1/user/settings/name', [
        'name' => 'test123'
    ]);
    $response->assertStatus(200);

    // upload avatar
    $response = $this->postJson('/api/v1/user/settings/avatar/upload', [
        'avatar' => UploadedFile::fake()->image('avatar.jpg')
    ]);
    $response->assertStatus(200);

    // half way check see got rewarded or not, should not be!
    $userMission = $this->user->missionsParticipating()->where('mission_id', $mission->id)
    ->first();

    // user should be in any mission since fully completed only count
    expect($userMission)->toBeNull();

    // update gender
    $response = $this->postJson('/api/v1/user/settings/gender', [
        'gender' => 'male'
    ]);
    $response->assertStatus(200);

    // update dob
    $date = [
        'year' => 1990,
        'month' => fake()->date('m'),
        'day' => fake()->date('d'),
    ];
    $response = $this->postJson('/api/v1/user/settings/dob', [
        'year' =>  (int) $date['year'],
        'month' => (int) $date['month'],
        'day' => rand(1, 28),
    ]);
    $response->assertStatus(200);

    $articleCategory = ArticleCategory::factory()->count(10)->create();
    $parentCategory = ArticleCategory::factory()->create();
    // assign to article category
    $articleCategory->each(function ($category) use ($parentCategory) {
        $category->parent()->associate($parentCategory);
        $category->save();
    });
    $response = $this->postJson('/api/v1/user/settings/article_categories', [
        'category_ids' => $articleCategory->pluck('id')->toArray()
    ]);
    $response->assertStatus(200);

    // fully completed profile
    $userMission = $this->user->missionsParticipating()->where('mission_id', $mission->id)
    ->first();

    // user should be in any mission since fully completed only count
    expect($userMission)->not->toBeNull();

    // check user mission process current_values
    if (is_string($userMission->pivot->current_values)) {
        $userMission->pivot->current_values = json_decode($userMission->pivot->current_values, true);
    }

    // expect current_values to be 1
    expect(array_values($userMission->pivot->current_values)[0])->toBe(1);

    // expect is_completed = 1
    expect($userMission->pivot->is_completed)->toBe(1);

    // expect user to get rewarded 1 egg
    $response = $this->getJson('/api/v1/points/components/balance?type=鸡蛋');
    expect($response->status())->toBe(200);

    // check if response has 鸡蛋 => 1
    expect($response->json('balance'))->toBe(1);
});

// test follower 5 users should get an egg
it('User can follow 5 users and get rewarded by mission', function () {
    Notification::fake();

    $users = User::factory()->count(5)->create();
    $component = RewardComponent::where('name', '鸡蛋')->first();
    $mission = Mission::factory()->create([
        'name' => 'Follow 5 users',
        'enabled_at' => now(),
        'description' => 'Follow 5 users',
        'events' => json_encode(['follow_a_user']),
        'values' => json_encode([5]),
        'missionable_type' => RewardComponent::class,
        'missionable_id' => $component->id,
        'reward_quantity' => 1,
        'reward_limit' => 100,
        'frequency' => 'one-off',
        'status' => 1,
        'auto_disburse_rewards' => 1, // make sure set auto disburse on if want to check rewards immediately
        'user_id' => $this->user->id
    ]);

    foreach ($users as $user) {
        // follow each users
        $response = $this->postJson('/api/v1/user/follow', [
            'user_id' => $user->id,
        ]);
        $response->assertStatus(200);
    }

    // check user mission process current_values
    $userMission = $this->user->missionsParticipating()->where('mission_id', $mission->id)
        ->first();

    // if current values is string, then decode first
    if (is_string($userMission->pivot->current_values)) {
        $userMission->pivot->current_values = json_decode($userMission->pivot->current_values, true);
    }

    // expect current_values to be 5
    expect(array_values($userMission->pivot->current_values)[0])->toBe(5);

    // expect pivot is_completed is true
    expect($this->user->missionsParticipating->first()->pivot->is_completed)
        ->toBe(1);

    // check notification fired
    Notification::assertSentTo(
        [$this->user],
        \App\Notifications\RewardReceivedNotification::class
    );

    // check user has received reward component
    $response = $this->getJson('/api/v1/points/components/balance?type=鸡蛋');
    expect($response->status())->toBe(200);
    expect($response->json('balance'))->toBe(1);
});

// test if user can accumulate 5 followers and get an egg
it('User can accumulate 5 followers and get rewarded by mission', function () {
    Notification::fake();

    $users = User::factory()->count(5)->create();
    $component = RewardComponent::where('name', '鸡蛋')->first();
    $mission = Mission::factory()->create([
        'name' => 'Accumulate 5 followers',
        'enabled_at' => now(),
        'description' => 'Accumulate 5 followers',
        'events' => json_encode(['accumulated_followers']),
        'values' => json_encode([5]),
        'missionable_type' => RewardComponent::class,
        'missionable_id' => $component->id,
        'reward_quantity' => 1,
        'reward_limit' => 100,
        'frequency' => 'one-off',
        'status' => 1,
        'auto_disburse_rewards' => 1, // make sure set auto disburse on if want to check rewards immediately
        'user_id' => $this->user->id
    ]);

    foreach ($users as $user) {
        // follow each users
        $this->actingAs($user);

        // follow ori user
        $response = $this->postJson('/api/v1/user/follow', [
            'user_id' => $this->user->id,
        ]);
        $response->assertStatus(200);
    }

    $this->actingAs($this->user);

    // check user mission process current_values
    $userMission = $this->user->missionsParticipating()->where('mission_id', $mission->id)
        ->first();

    // if current values is string, then decode first
    if (is_string($userMission->pivot->current_values)) {
        $userMission->pivot->current_values = json_decode($userMission->pivot->current_values, true);
    }

    // expect current_values to be 5
    expect(array_values($userMission->pivot->current_values)[0])->toBe(5);

    // expect pivot is_completed is true
    expect($this->user->missionsParticipating->first()->pivot->is_completed)
        ->toBe(1);

    // check notification fired
    Notification::assertSentTo(
        [$this->user],
        \App\Notifications\RewardReceivedNotification::class
    );

    // check user has received reward component
    $response = $this->getJson('/api/v1/points/components/balance?type=鸡蛋');
    expect($response->status())->toBe(200);
    expect($response->json('balance'))->toBe(1);
});

// test if user can bookmark 5 articles and get an egg
it('User can bookmark 5 articles and get rewarded by mission', function () {
    Notification::fake();

    $articles = Article::factory()->count(5)->create();

    // get reward component
    $component = RewardComponent::where('name', '鸡蛋')->first();
    $mission = Mission::factory()->create([
        'name' => 'Bookmark 5 articles',
        'enabled_at' => now(),
        'description' => 'Bookmark 5 articles',
        'events' => json_encode(['bookmark_an_article']),
        'values' => json_encode([5]),
        'missionable_type' => RewardComponent::class,
        'missionable_id' => $component->id,
        'reward_quantity' => 1,
        'reward_limit' => 100,
        'frequency' => 'one-off',
        'status' => 1,
        'auto_disburse_rewards' => 1, // make sure set auto disburse on if want to check rewards immediately
        'user_id' => $this->user->id
    ]);

    foreach ($articles as $article) {
        // bookmark each articles
        $response = $this->postJson('/api/v1/interactions', [
            'id' => $article->id,
            'interactable' => 'article',
            'type' => 'bookmark',
        ]);
        $response->assertStatus(200);
    }

    // check user mission process current_values
    $userMission = $this->user->missionsParticipating()->where('mission_id', $mission->id)
        ->first();

    // if current values is string, then decode first
    if (is_string($userMission->pivot->current_values)) {
        $userMission->pivot->current_values = json_decode($userMission->pivot->current_values, true);
    }

    // expect current_values to be 5
    expect(array_values($userMission->pivot->current_values)[0])->toBe(5);

    // expect pivot is_completed is true
    expect($this->user->missionsParticipating->first()->pivot->is_completed)
        ->toBe(1);

    // check notification fired
    Notification::assertSentTo(
        [$this->user],
        \App\Notifications\RewardReceivedNotification::class
    );

    // check user has received reward component
    $response = $this->getJson('/api/v1/points/components/balance?type=鸡蛋');
    expect($response->status())->toBe(200);
    expect($response->json('balance'))->toBe(1);
});

it('automatically disburses rewards and sets claimed fields for non-auto-disbursed missions with multiple instances', function () {
    Notification::fake();

    $articles = Article::factory()->count(10)->create();
    $component = RewardComponent::where('name', '鸡蛋')->first();

    $mission = Mission::factory()->create([
        'name' => 'Comment on 10 articles',
        'enabled_at' => now(),
        'description' => 'Comment on 10 articles',
        'events' => json_encode(['comment_created']),
        'values' => json_encode([10]),
        'missionable_type' => RewardComponent::class,
        'missionable_id' => $component->id,
        'reward_quantity' => 1,
        'frequency' => 'daily',
        'status' => 1,
        'auto_disburse_rewards' => 0, // Set auto_disburse_rewards to 0 so suer need self claim rewards
        'user_id' => $this->user->id
    ]);

    // Create multiple instances of the same mission for the user on different days
    $this->user->missionsParticipating()->attach($mission->id, [
        'started_at' => now()->subDays(2),
        'current_values' => json_encode([0]),
        'is_completed' => 0,
        'created_at' => now()->subDays(2),
        'updated_at' => now()->subDays(2),
    ]);
    $this->user->missionsParticipating()->attach($mission->id, [
        'started_at' => now(),
        'current_values' => json_encode([0]),
        'is_completed' => 0,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    foreach ($articles as $article) {
        $response = $this->postJson('/api/v1/comments', [
            'id' => $article->id,
            'type' => 'article',
            'body' => 'test comment',
            'parent_id' => null
        ]);
    }

    // Refresh the user instance to get the latest data
    $this->user->refresh();

    // Check user mission process current_values
    $userMissions = $this->user->missionsParticipating()->where('mission_id', $mission->id)->get();

    foreach ($userMissions as $userMission) {
        expect($userMission->pivot->is_completed)->toBe(1);
        expect($userMission->pivot->claimed_at)->toBeNull(); // claimed_at should be null since rewards are not auto-disbursed
    }

    // Call the complete mission endpoint for the latest mission instance
    $response = $this->postJson('/api/v1/missions/complete', ['mission_id' => $mission->id]);
    $response->assertStatus(200);

    // Refresh the user instance again to get the updated data
    $this->user->refresh();

    // Check if the claimed_at field is updated for the latest mission instance
    $userMissions = $this->user->missionsParticipating()->where('mission_id', $mission->id)->get();

    // check if MissionRewardDisbursement record is created
    $this->assertDatabaseHas('mission_reward_disbursements', [
        'mission_id' => $mission->id,
        'user_id' => $this->user->id,
        'reward_quantity' => $mission->reward_quantity
    ]);

    // dd($userMissions->toArray());

    foreach ($userMissions as $userMission) {
        if ($userMission->pivot->id == $userMissions->last()->pivot->id) {
            expect($userMission->pivot->claimed_at)->not->toBeNull();
        } else {
            expect($userMission->pivot->claimed_at)->toBeNull();
        }
    }

    // Check if mission data includes claimed fields
    $response = $this->getJson('/api/v1/missions?completed_only=1');

    $response->assertStatus(200);

    $response->assertJsonFragment([
        'id' => $mission->id,
        'is_completed' => true,
        'claimed' => true,
    ]);
    $response->assertJsonStructure([
        'data' => [
            '*' => [
                'claimed_at',
                'claimed_at_formatted',
            ]
        ]
    ]);

    // load completed_only=0 should not show this mission->id
    $response = $this->getJson('/api/v1/missions?completed_only=0');
    $response->assertStatus(200);

    $this->assertNotContains($mission->id, collect($response->json('data'))->pluck('id')->toArray());

    // assert meta.total is 0
    $this->assertEquals(0, $response->json('meta.total'));
});

it('User can participate in an accumulated mission only once until reaching the goal', function () {
    Notification::fake();

    $articles = Article::factory()->count(5)->create();

    $component = RewardComponent::where('name', '鸡蛋')->first();

    $mission = Mission::factory()->create([
        'name' => 'Comment on 10 articles (Accumulated)',
        'enabled_at' => now(),
        'description' => 'Comment on 10 articles (Accumulated)',
        'events' => json_encode(['comment_created']),
        'values' => json_encode([10]),
        'missionable_type' => RewardComponent::class,
        'missionable_id' => $component->id,
        'reward_quantity' => 1,
        'frequency' => 'accumulated',
        'status' => 1,
        'auto_disburse_rewards' => 1,
        'user_id' => $this->user->id
    ]);

    // Comment on 5 articles
    foreach ($articles as $article) {
        $response = $this->postJson('/api/v1/comments', [
            'id' => $article->id,
            'type' => 'article',
            'body' => 'Test comment',
            'parent_id' => null
        ]);
    }

    // Check user mission progress current_values
    $userMission = $this->user->missionsParticipating()->where('mission_id', $mission->id)->first();

    if (is_string($userMission->pivot->current_values)) {
        $userMission->pivot->current_values = json_decode($userMission->pivot->current_values, true);
    }

    expect(array_values($userMission->pivot->current_values)[0])->toBe(5);
    expect($userMission->pivot->is_completed)->toBe(0);

    // Create 5 more articles
    $moreArticles = Article::factory()->count(5)->create();

    // Comment on the additional 5 articles
    foreach ($moreArticles as $article) {
        $response = $this->postJson('/api/v1/comments', [
            'id' => $article->id,
            'type' => 'article',
            'body' => 'Test comment',
            'parent_id' => null
        ]);
    }

    // Refresh the user instance
    $this->user->refresh();

    // Check user mission progress current_values
    $userMission = $this->user->missionsParticipating()->where('mission_id', $mission->id)->first();

    if (is_string($userMission->pivot->current_values)) {
        $userMission->pivot->current_values = json_decode($userMission->pivot->current_values, true);
    }

    expect(array_values($userMission->pivot->current_values)[0])->toBe(10);
    expect($userMission->pivot->is_completed)->toBe(1);

    // Check if the user received the reward
    $response = $this->getJson('/api/v1/points/components/balance?type=鸡蛋');
    expect($response->status())->toBe(200);
    expect($response->json('balance'))->toBe(1);

    // Check if notification was sent
    Notification::assertSentTo(
        [$this->user],
        \App\Notifications\RewardReceivedNotification::class
    );

    // Check if only one mission_user record exists for the specific mission and user
    $missionUserCount = DB::table('missions_users')
        ->where('mission_id', $mission->id)
        ->where('user_id', $this->user->id)
        ->count();

    expect($missionUserCount)->toBe(1);

    // wait 3 seconds
    sleep(3);

    // Restart the mission by commenting on 10 more articles
    $restartArticles = Article::factory()->count(10)->create();

    foreach ($restartArticles as $article) {
        $response = $this->postJson('/api/v1/comments', [
            'id' => $article->id,
            'type' => 'article',
            'body' => 'Test comment',
            'parent_id' => null
        ]);
    }

    // Refresh the user instance
    $this->user->refresh();

    // Check user mission progress current_values after restarting
    $userMission = $this->user->missionsParticipating()->where('mission_id', $mission->id)->latest()->first();

    if (is_string($userMission->pivot->current_values)) {
        $userMission->pivot->current_values = json_decode($userMission->pivot->current_values, true);
    }

    expect(array_values($userMission->pivot->current_values)[0])->toBe(10);
    expect($userMission->pivot->is_completed)->toBe(1);

    // Check if the user received the reward again
    // $response = $this->getJson('/api/v1/points/components/balance?type=鸡蛋');
    // expect($response->status())->toBe(200);

    // expect($response->json('balance'))->toBe(2);

    // Check if two mission_user records exist for the specific mission and user
    $missionUserCount = DB::table('missions_users')
        ->where('mission_id', $mission->id)
        ->where('user_id', $this->user->id)
        ->count();

    expect($missionUserCount)->toBe(2);

    // Check if the completed_at value is correctly set for the second (latest) record
    $latestMissionUser = DB::table('missions_users')
        ->where('mission_id', $mission->id)
        ->where('user_id', $this->user->id)
        ->latest()
        ->first();

    expect($latestMissionUser->completed_at)->not->toBeNull();

    // Check if the completed_at value of the first record is not overridden
    $firstMissionUser = DB::table('missions_users')
        ->where('mission_id', $mission->id)
        ->where('user_id', $this->user->id)
        ->oldest()
        ->first();

    expect($firstMissionUser->completed_at)->not->toBeNull();
    expect($firstMissionUser->id)->not->toEqual($latestMissionUser->id);
});

it('User can like a comment and get rewarded by mission', function () {
    Notification::fake();

    // Create an article
    $article = Article::factory()->create();

    // Create comments associated with the article and the logged-in user
    $comments = Comment::factory()->count(5)->create([
        'commentable_id' => $article->id,
        'commentable_type' => Article::class,
        'user_id' => $this->user->id,
    ]);

    // get reward component
    $component = RewardComponent::where('name', '鸡蛋')->first();
    $mission = Mission::factory()->create([
        'name' => 'Like 5 comments',
        'enabled_at' => now(),
        'description' => 'Like 5 comments',
        'events' => json_encode(['like_comment']),
        'values' => json_encode([5]),
        'missionable_type' => RewardComponent::class,
        'missionable_id' => $component->id,
        'reward_quantity' => 1,
        'reward_limit' => 100,
        'frequency' => 'one-off',
        'status' => 1,
        'auto_disburse_rewards' => 1,
        'user_id' => $this->user->id
    ]);

    foreach ($comments as $comment) {
        // like each comment
        $response = $this->postJson('/api/v1/comments/like_toggle', [
            'comment_id' => $comment->id,
        ]);
        $response->assertStatus(200);
    }

    // check user mission process current_values
    $userMission = $this->user->missionsParticipating()->where('mission_id', $mission->id)
        ->first();

    // if current values is string, then decode first
    if (is_string($userMission->pivot->current_values)) {
        $userMission->pivot->current_values = json_decode($userMission->pivot->current_values, true);
    }

    // expect current_values to be 5
    expect(array_values($userMission->pivot->current_values)[0])->toBe(5);

    // expect pivot is_completed is true
    expect($this->user->missionsParticipating->first()->pivot->is_completed)
        ->toBe(1);

    // check notification fired
    Notification::assertSentTo(
        [$this->user],
        \App\Notifications\RewardReceivedNotification::class
    );

    // check user has received reward component
    $response = $this->getJson('/api/v1/points/components/balance?type=鸡蛋');
    expect($response->status())->toBe(200);
    expect($response->json('balance'))->toBe(1);
});
