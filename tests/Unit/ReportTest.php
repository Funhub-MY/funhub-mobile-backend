<?php

namespace Tests\Unit;

use App\Models\Article;
use App\Models\Comment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;


uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();

    $this->article = Article::factory()->create();
    // create comment for article above;
    $this->comment = Comment::create([
        'user_id' => $this->user->id,
        'commentable_type' => get_class($this->article),
        'commentable_id' => $this->article->id,
        'body' => 'Testing Hate Speech Comment.',
        'parent_id' => null,
        'status' => Comment::STATUS_PUBLISHED, // DEFAULT ALL PUBLISHED
    ]);
    Sanctum::actingAs($this->user,['*']);
});

it('has reported a comment', function () {
    $body = [];
    // test validation
    $response = $this->postJson('/api/v1/comments/report', $body);
    $response->assertStatus(422)
        ->assertJsonStructure([
           'message'
        ]);
    // re-initializes body param.
    $body = [
        'comment_id' => $this->comment->id,
        'reason' => 'Giving hate speech',
        'violation_type' => 'Violence and Criminal Behavior, Inappropriate Speech or Content',
        'violation_level' => 1
    ];

    $response = $this->postJson('/api/v1/comments/report', $body);

    $response->assertStatus(200)
        ->assertJsonStructure([
            'message'
        ]);

    // check report.

    $this->assertDatabaseHas('reports', [
        'reportable_type' => get_class($this->comment),
        'reportable_id' => $this->comment->id,
        'violation_level' => 1,
        'violation_type' => 'Violence and Criminal Behavior, Inappropriate Speech or Content',
        'reason' => 'Giving hate speech'
    ]);

});
it('does not have repeated comment reported', function() {
    // report comment first.
    $response = $this->postJson('/api/v1/comments/report', [
        'comment_id' => $this->comment->id,
        'reason' => 'Giving hate speech',
        'violation_type' => 'Violence and Criminal Behavior, Inappropriate Speech or Content',
        'violation_level' => 1,
    ]);
    // repeated report.
    $repeated_response = $this->postJson('/api/v1/comments/report', [
        'comment_id' => $this->comment->id,
        'reason' => 'Giving hate speech',
        'violation_type' => 'Violence and Criminal Behavior, Inappropriate Speech or Content',
        'violation_level' => 1,
    ]);
    // 422 as the same repeated has been done by this user.
    $repeated_response->assertStatus(422)
        ->assertJsonStructure([
            'message'
        ]);
});
it('has reported an article', function() {
    $body = [];
    // test validation
    $response = $this->postJson('/api/v1/articles/report', $body);
    $response->assertStatus(422)
        ->assertJsonStructure([
            'message'
        ]);
    $body = [
        'article_id' => $this->article->id,
        'reason' => 'Giving hate speech',
        'violation_type' => 'Violence and Criminal Behavior, Inappropriate Speech or Content',
        'violation_level' => 1,
    ];
    // post another time.
    $response = $this->postJson('/api/v1/articles/report', $body);
    // check responses status
    $response->assertStatus(200)
        ->assertJsonStructure([
            'message'
        ]);
    // check report.
    $this->assertDatabaseHas('reports', [
        'reportable_type' => get_class($this->article),
        'reportable_id' => $this->article->id,
        'violation_level' => 1,
        'violation_type' => 'Violence and Criminal Behavior, Inappropriate Speech or Content',
        'reason' => 'Giving hate speech'
    ]);
});
it('does not have repeated article reported', function () {
    $response = $this->postJson('/api/v1/articles/report', [
        'article_id' => $this->article->id,
        'reason' => 'Giving hate speech',
        'violation_type' => 'Violence and Criminal Behavior, Inappropriate Speech or Content',
        'violation_level' => 1,
    ]);

    $repeated_response = $this->postJson('/api/v1/articles/report', [
        'article_id' => $this->article->id,
        'reason' => 'Giving hate speech',
        'violation_type' => 'Violence and Criminal Behavior, Inappropriate Speech or Content',
        'violation_level' => 1,
    ]);
    // 422 as the same repeated has been done by this user.
    $repeated_response->assertStatus(422)
        ->assertJsonStructure([
            'message'
        ]);
});

it('has reported an user', function () {
    $targetted_user = User::factory()->create();
    $body = [];
    // test validation
    $response = $this->postJson('/api/v1/user/report', $body);
    $response->assertStatus(422)
        ->assertJsonStructure([
            'message'
        ]);
    $body = [
        'user_id' => $targetted_user->id,
        'reason' => 'Giving hate speech',
        'violation_type' => 'Violence and Criminal Behavior, Inappropriate Speech or Content',
        'violation_level' => 1,
    ];
    // post another time.
    $response = $this->postJson('/api/v1/user/report', $body);
    // check responses status
    $response->assertStatus(200)
        ->assertJsonStructure([
            'message'
        ]);
    // check report.
    $this->assertDatabaseHas('reports', [
        'reportable_type' => get_class($targetted_user),
        'reportable_id' => $targetted_user->id,
        'violation_level' => 1,
        'violation_type' => 'Violence and Criminal Behavior, Inappropriate Speech or Content',
        'reason' => 'Giving hate speech'
    ]);
});

it('does not have repeated user reported', function () {
    $response = $this->postJson('/api/v1/user/report', [
        'user_id' => $this->user->id,
        'reason' => 'Giving hate speech',
        'violation_type' => 'Violence and Criminal Behavior, Inappropriate Speech or Content',
        'violation_level' => 1,
    ]);

    $repeated_response = $this->postJson('/api/v1/user/report', [
        'user_id' => $this->user->id,
        'reason' => 'Giving hate speech',
        'violation_type' => 'Violence and Criminal Behavior, Inappropriate Speech or Content',
        'violation_level' => 1,
    ]);
    // 422 as the same repeated has been done by this user.
    $repeated_response->assertStatus(422)
        ->assertJsonStructure([
            'message'
        ]);
});
