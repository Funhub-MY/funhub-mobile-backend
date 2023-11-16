<?php

namespace Tests\Unit;

use App\Mail\EmailVerification;
use Tests\TestCase;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Laravel\Sanctum\Sanctum;

class UserTest extends TestCase
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
     * Test auth user details
     */
    public function testGetAuthUserDetails() {
        $user = User::factory()->create();
        Sanctum::actingAs($user,['*']);

        $response = $this->getJson("/api/v1/user");
        //dd($response->json());
        
        $response->assertStatus(200);

        $response->assertJsonStructure([
            'user' => [
                'id',
                'name',
                'email',
                'verified_email',
                'auth_provider',
                'avatar',
                'avatar_thumb',
                'bio',
                'cover',
                'articles_published_count',
                'following_count',
                'followers_count',
                'has_completed_profile',
                'has_avatar',
                'point_balance',
                'unread_notifications_count',
                'is_following',
                'dob',
                'gender',
                'job_title',
                'country_id',
                'state_id',
                'category_ids',
            ],
            'token',
        ]);
        
    }

}
