<?php

namespace Tests\Unit;

use App\Mail\EmailVerification;
use Tests\TestCase;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;

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
    // public function testSendOtpAndVerifyOtpAsNewUser()
    // {
    //     $response = $this->postJson('/api/v1/sendOtp', [
    //         'country_code' => '60',
    //         'phone_no' => '1234567890' // fake phone number
    //     ]);

    //     // find User created
    //     $user = User::where('phone_no', '1234567890')
    //         ->where('phone_country_code', '60')
    //         ->first();

    //     // verify otp

    //     $response = $this->postJson('/api/v1/verifyOtp', [
    //         'country_code' => '60',
    //         'phone_no' => '1234567890', // fake phone number
    //         'otp' => $user->otp
    //     ]);

    //     $response->assertStatus(200)
    //         ->assertJsonStructure([
    //             'user', 'token'
    //         ]);

    //     // verify user is created
    //     $this->assertDatabaseHas('users', [
    //         'phone_no' => '1234567890',
    //         'phone_country_code' => '60',
    //         'otp' => null,
    //         'otp_verified_at' => now()->toDateTimeString()
    //     ]);
    // }

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
     * Test Register with OTP success with Email Verification Token as well
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

        // send email verification
        $response = $this->postJson('/api/v1/user/send-email-verification', [
            'email' => 'john@gmail.com',
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure(['message']);

        // get the token from the email
        $token = User::where('email', 'john@gmail.com')->first()->email_verification_token;

        // verify email address
        $response = $this->postJson('/api/v1/user/verify-email', [
            'token' => $token,
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure(['message']);

        // check database has email verified at for this user
        $this->assertDatabaseHas('users', [
            'email' => 'john@gmail.com',
            'email_verified_at' => now()
        ]);

        // complete profile
        $response = $this->postJson('/api/v1/user/complete-profile', [
            'name' => 'John Doe',
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
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'message'
            ]);

        // verify user is updated
        $this->assertDatabaseHas('users', [
            'name' => 'John Doe',
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

    /**
     * Test complete profile but user is suspended status is 0
     * /api/v1/user/sendOtp
     */
    public function testSmsOtpWithWrongPhoneNo()
    {
        $response = $this->postJson('/api/v1/sendOtp', [
            'country_code' => '60',
            'phone_no' => '01234567890' // fake phone number with zero infront
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'message'
            ]);

        // check database ahas this phone no without zero at front
        $this->assertDatabaseHas('users', [
            'phone_no' => '1234567890',
            'phone_country_code' => '60'
        ]);
    }
}
