<?php

namespace Tests\Unit;

use App\Models\User;
use PHPUnit\Framework\TestCase;

class AuthTest extends TestCase
{
    /**
     * Test /api/v1/login with invalid email and password assert response 422
     */
    public function test_failed_login_with_invalid_email() {
        $response = $this->postJson('/api/v1/login', [
            'email' => 'somerandomemail@gmail.com',
            'password' => 'password'
        ]);

        $response->assertStatus(422);
    }

    /**
     * Test /api/v1/login with email and password assert response 200 with token
     */
    public function test_successful_login_with_email() {
        $user = User::factory()->create();

        $response = $this->postJson('/api/v1/login', [
            'email' => $user->email,
            'password' => 'abcd1234'
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'user',
                'token'
            ]);
    }

    /**
     * Test /api/v1/register/email with email and password assert response 200 with token
     */
    public function test_successful_register_with_email() {
        $response = $this->postJson('/api/v1/register/email', [
            'name' => 'John Smith',
            'email' => 'john@smith.com',
            'password' => 'abcd1234'
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'user',
                'token'
            ]);
    }
}
