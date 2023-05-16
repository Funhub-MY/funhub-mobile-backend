<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
    }

    /**
     * Test SendOtp API 
     */
    public function testSendOtp() {
        $response = $this->postJson('/api/v1/sendOtp', [
            'country_code' => '60',
            'phone_no' => '1234567890' // fake phone number
        ]);

        $response->assertStatus(200)
            ->assertExactJson([
                'message' => 'OTP sent'
            ]);
    }

    /**
     * Test SendOtp API with invalid phone number
     */
    public function testSendOtpWithoutPhoneNo() {
        $response = $this->postJson('/api/v1/sendOtp', [
            'country_code' => '60',
        ]);

        $response->assertStatus(422)
            ->assertJsonStructure([
                'errors' => [
                    'phone_no'
                ]
            ]);
    }

    /**
     * Test SendOtp API with invalid country code
     */
    public function testSendOtpWithoutCountryCode() {
        $response = $this->postJson('/api/v1/sendOtp', [
            'phone_no' => '1234567890' // fake phone number
        ]);

        $response->assertStatus(422)
            ->assertJsonStructure([
                'errors' => [
                    'country_code'
                ]
            ]);
    }

    /**
     * Test Send OTP and Verify OTP flow as new user (success)
     */
    public function testSendOtpAndVerifyOtpAsNewUser()
    {
        $response = $this->postJson('/api/v1/sendOtp', [
            'country_code' => '60',
            'phone_no' => '1234567890' // fake phone number
        ]);

        // find User created
        $user = User::where('phone_no', '1234567890')
            ->where('phone_country_code', '60')
            ->first();

        // verify otp
        $response = $this->postJson('/api/v1/verifyOtp', [
            'country_code' => '60',
            'phone_no' => '1234567890', // fake phone number
            'otp' => $user->otp
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'user', 'token'
            ]);

        // verify user is created
        $this->assertDatabaseHas('users', [
            'phone_no' => '1234567890',
            'phone_country_code' => '60',
            'otp' => null,
            'otp_verified_at' => now()
        ]);
    }

    /**
     * Test Register with OTP success
     */
    public function testRegisterWithOtpSuccess() 
    {
        $this->refreshDatabase();
        $user = User::factory()->create([
            'otp' => '123456',
        ]);

        // basically this just updates user information
        $response = $this->postJson('/api/v1/register/otp', [
            'email' => $user->email,
            'country_code' => $user->phone_country_code,
            'phone_no' => (string) $user->phone_no, // fake phone number
            'otp' => $user->otp,
            'name' => $user->name,
            'password' => 'abcd1234',
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'user', 'token'
            ]);

        // verify database has this user with the name, password
        $this->assertDatabaseHas('users', [
            'email' => $user->email,
            'phone_no' => $user->phone_no,
            'phone_country_code' => $user->phone_country_code,
            'otp' => null,
            'otp_expiry' => null,
        ]);
    }

    /**
     * Test Register with OTP success
     * /api/v1/user/complete-profile
     */
    public function testCompleteProfile()
    {
        $response = $this->postJson('/api/v1/sendOtp', [
            'country_code' => '60',
            'phone_no' => '1234567890' // fake phone number
        ]);

        // find User created
        $user = User::where('phone_no', '1234567890')
            ->where('phone_country_code', '60')
            ->first();

        // verify otp
        $response = $this->postJson('/api/v1/verifyOtp', [
            'country_code' => '60',
            'phone_no' => '1234567890', // fake phone number
            'otp' => $user->otp
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'user', 'token'
            ]);

        // verify user is created
        $this->assertDatabaseHas('users', [
            'phone_no' => '1234567890',
            'phone_country_code' => '60',
            'otp' => null,
        ]);

        // complete profile
        $response = $this->postJson('/api/v1/user/complete-profile', [
            'name' => 'John Doe',
            'email' => 'john@gmail.com',
            'password' => 'abcd1234',
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'message'
            ]);

        // verify user is updated
        $this->assertDatabaseHas('users', [
            'phone_no' => '1234567890',
            'phone_country_code' => '60',
            'otp' => null,
            'name' => 'John Doe',
            'email' => 'john@gmail.com'
        ]);
    }

    /**
     * Test PostCompleteProfile if user has google_id or facebook_id populated no need password
     */
    public function testPostCompleteProfileWithGoogleId()
    {
        // create a user with google_id
        $user = User::factory()->create([
            'google_id' => '1234567890',
        ]);

        // act as this user in session
        $this->actingAs($user);

        // complete profile
        $response = $this->postJson('/api/v1/user/complete-profile', [
            'name' => 'John Doe',
            'email' => 'john@gmail.com',
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'message'
            ]);

        // verify user is updated
        $this->assertDatabaseHas('users', [
            'name' => 'John Doe',
            'email' => 'john@gmail.com',
            'google_id' => $user->google_id
        ]);
    }

    /**
     * Test complete profile but user is suspended status is 0
     */
    public function testPostCompleteProfileWithSuspendedUser()
    {
        // create a user with google_id
        $user = User::factory()->create([
            'status' => User::STATUS_SUSPENDED,
        ]);

        // act as this user in session
        $this->actingAs($user);

        // complete profile
        $response = $this->postJson('/api/v1/user/complete-profile', [
            'name' => 'John Doe',
            'email' => 'test@gmail.com',
            'password' => 'abcd1234'
        ]);
        
        $response->assertStatus(403);
    }
}
