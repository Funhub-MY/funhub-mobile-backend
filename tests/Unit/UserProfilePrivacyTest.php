<?php

namespace Tests\Unit;

use App\Models\ArticleCategory;
use App\Mail\EmailVerification;
use Tests\TestCase;
use App\Models\User;
use App\Models\Country;
use App\Models\State;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Laravel\Sanctum\Sanctum;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;

class UserProfilePrivacyTest extends TestCase
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

    public function testUpdateProfilePrivacy()
    {
        // reset database
        $this->user->profilePrivacySettings()->delete();

        // change to private
        $response = $this->postJson('/api/v1/user/settings/profile-privacy', [
            'profile_privacy' => 'private',
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'message' => 'Profile privacy updated',
            'profile_privacy' => 'private',
        ]);


        // change to public
        $response = $this->postJson('/api/v1/user/settings/profile-privacy', [
            'profile_privacy' => 'public',
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'message' => 'Profile privacy updated',
            'profile_privacy' => 'public',
        ]);

        // change to public again
        $response = $this->postJson('/api/v1/user/settings/profile-privacy', [
            'profile_privacy' => 'public',
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'message' => 'Profile privacy already set to public',
            'profile_privacy' => 'public',
        ]);
    }

}
