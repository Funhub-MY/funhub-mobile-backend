<?php

use App\Models\ArticleCategory;
use App\Models\Country;
use App\Models\State;
use App\Models\User;
use App\Models\UserSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
    Sanctum::actingAs($this->user, ['*']);
});

test('get user settings', function () {
    $response = $this->getJson('/api/v1/user/settings');

    $response->assertStatus(200)
        ->assertJsonStructure([
            'name',
            'username',
            'email',
            'dob',
            'gender',
            'bio',
            'job_title',
            'country_id',
            'state_id',
            'avatar',
            'avatar_thumb',
            'category_ids'
        ]);
});

test('assign categories to a user', function () {
    $articleCategory = ArticleCategory::factory()->count(10)->create();

    $response = $this->postJson('/api/v1/user/settings/article_categories', [
        'category_ids' => $articleCategory->pluck('id')->toArray()
    ]);

    $response->assertStatus(200)
        ->assertJsonStructure([
            'message'
        ]);
});

test('upload user avatar', function () {
    $response = $this->postJson('/api/v1/user/settings/avatar/upload', [
        'avatar' => UploadedFile::fake()->image('avatar.jpg')
    ]);

    $response->assertStatus(200)
        ->assertJsonStructure([
            'message', 'avatar', 'avatar_thumb'
        ]);

    $this->assertDatabaseHas('users', [
        'id' => $this->user->id,
        'avatar' => $response->json('avatar_id'),
    ]);
});

test('save email', function () {
    $response = $this->postJson('/api/v1/user/settings/email', [
        'email' => 'test123@gmail.com'
    ]);

    $response->assertStatus(200)
        ->assertJsonStructure([
            'message'
        ]);

    $this->assertDatabaseHas('users', [
        'id' => $this->user->id,
        'email' => 'test123@gmail.com',
    ]);
});

test('save name', function () {
    $response = $this->postJson('/api/v1/user/settings/name', [
        'name' => 'test123'
    ]);

    $response->assertStatus(200)
        ->assertJsonStructure([
            'message'
        ]);

    $this->assertDatabaseHas('users', [
        'id' => $this->user->id,
        'name' => 'test123',
    ]);
});

test('save bio', function () {
    $bio = fake()->paragraph(3);

    $response = $this->postJson('/api/v1/user/settings/bio', [
        'bio' => $bio
    ]);

    $response->assertStatus(200)
        ->assertJsonStructure([
            'message'
        ]);

    $this->assertDatabaseHas('users', [
        'id' => $this->user->id,
        'bio' => $bio,
    ]);
});

test('save date of birth', function () {
    $date = [
        'year' => 1990,
        'month' => fake()->date('m'),
        'day' => fake()->date('d'),
    ];

    $response = $this->postJson('/api/v1/user/settings/dob', [
        'year' =>  (int) $date['year'],
        'month' => (int) $date['month'],
        'day' => (int) $date['day'],
    ]);

    $response->assertStatus(200)
        ->assertJsonStructure([
            'message'
        ]);

    $this->assertDatabaseHas('users', [
        'id' => $this->user->id,
        'dob' => $date['year'].'-'.$date['month'].'-'.$date['day'],
    ]);
});

test('save gender', function () {
    $response = $this->postJson('/api/v1/user/settings/gender', [
        'gender' => 'male'
    ]);

    $response->assertStatus(200)
        ->assertJsonStructure([
            'message'
        ]);

    $this->assertDatabaseHas('users', [
        'id' => $this->user->id,
        'gender' => 'male'
    ]);
});

test('save job title', function () {
    $response = $this->postJson('/api/v1/user/settings/job-title', [
        'job_title' => 'Software Engineer'
    ]);

    $response->assertStatus(200)
        ->assertJsonStructure([
            'message'
        ]);

    $this->assertDatabaseHas('users', [
        'id' => $this->user->id,
        'job_title' => 'Software Engineer'
    ]);
});

test('save location', function () {
    $this->seed('CountriesTableSeeder');
    $this->seed('StatesTableSeeder');

    $country = Country::where('code', 'MY')->first();
    $state = State::where('country_id', $country->id)->first();

    $response = $this->postJson('/api/v1/user/settings/location', [
        'country_id' => $country->id,
        'state_id' => $state->id,
    ]);

    $response->assertStatus(200)
        ->assertJsonStructure([
            'message'
        ]);

    $this->assertDatabaseHas('users', [
        'id' => $this->user->id,
        'country_id' => $country->id,
        'state_id' => $state->id,
    ]);
});