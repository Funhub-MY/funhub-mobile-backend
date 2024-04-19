<?php
namespace Tests\Unit;

use App\Events\UserReferred;
use Tests\TestCase;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Laravel\Sanctum\Sanctum;

class ReferralTest extends TestCase
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

    public function testGetMyReferralCode()
    {
        $response = $this->getJson('/api/v1/user/settings/referrals/my-code');
        $response->assertStatus(200);

        $response->assertJsonStructure([
            'referral_code',
            'message'
        ]);

        // check code match in database or not
        $this->assertDatabaseHas('users', [
            'id' => $this->user->id,
            'referral_code' => $response['referral_code']
        ]);
    }

    public function testReferredByAUser()
    {
        $referredBy = User::factory()->create();

        // to generate code must have go to my-code once
        $this->actingAs($referredBy);
        $response = $this->getJson('/api/v1/user/settings/referrals/my-code');
        $response->assertStatus(200);

        // assert code exists
        $this->assertDatabaseHas('users', [
            'id' => $referredBy->id,
            'referral_code' => $response['referral_code']
        ]);

        $code = $response['referral_code'];

        // log in back as the main user
        $this->actingAs($this->user);
        $response = $this->postJson('/api/v1/user/settings/referrals/save', [
            'referral_code' => $code
        ]);
        $response->assertStatus(200);

        // check referred by id in database or not
        $this->assertDatabaseHas('users', [
            'id' => $this->user->id,
            'referred_by_id' => $referredBy->id
        ]);

        // check if user input again should fail
        $response = $this->postJson('/api/v1/user/settings/referrals/save', [
            'referral_code' => $code
        ]);
        $response->assertStatus(422);

        // check see both has funhub credited
        $response = $this->getJson('/api/v1/points/balance');
        expect($response->status())->toBe(200);
        expect($response['balance'])->toBe(1);

        // log back in as referredBy
        $this->actingAs($referredBy);
        $response = $this->getJson('/api/v1/points/balance');
        expect($response->status())->toBe(200);
        expect($response['balance'])->toBe(1);
    }

    // if a user is more than 48hours old config('app.referral_max_hours') cannot use referral api anymore
    public function testReferredByAUserOlderThan48Hours()
    {
        $this->user->update([
            'created_at' => now()->subHours(config('app.referral_max_hours'))->subMinutes(1)
        ]);

        $referredBy = User::factory()->create();

        // to generate code must have go to my-code once
        $this->actingAs($referredBy);
        $response = $this->getJson('/api/v1/user/settings/referrals/my-code');
        $response->assertStatus(200);

        // assert code exists
        $this->assertDatabaseHas('users', [
            'id' => $referredBy->id,
            'referral_code' => $response['referral_code']
        ]);

        $code = $response['referral_code'];

        // log in back as the main user
        $this->actingAs($this->user);
        $response = $this->postJson('/api/v1/user/settings/referrals/save', [
            'referral_code' => $code
        ]);
        $response->assertStatus(422);

        // check referred by id in database or not
        $this->assertDatabaseMissing('users', [
            'id' => $this->user->id,
            'referred_by_id' => $referredBy->id
        ]);

        // check see both has funhub credited
        $response = $this->getJson('/api/v1/points/balance');
        expect($response->status())->toBe(200);
        expect($response['balance'])->toBe(0);

        // log back in as referredBy
        $this->actingAs($referredBy);
        $response = $this->getJson('/api/v1/points/balance');
        expect($response->status())->toBe(200);
        expect($response['balance'])->toBe(0);
    }

}
