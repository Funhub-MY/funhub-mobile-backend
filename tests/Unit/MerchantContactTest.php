<?php

use Tests\TestCase;
use App\Models\User;
use App\Models\MerchantContact;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

class MerchantContactTest extends TestCase
{
    use RefreshDatabase;
    protected $user;

    public function setUp(): void
    {
        parent::setUp();

        // reset database
        $this->refreshDatabase();

        // mock log in user get token
        $this->user = User::factory()->create();
        Sanctum::actingAs($this->user,['*']);
    }

    public function testPostMerchantContactSuccess()
    {
        $data = [
            'name' => 'Alex Koh',
            'email' => 'alex@funhub.my',
            'tel_no' => '182036794',
            'company_name' => 'Funhub TV',
            'business_type' => 'others',
            'other_business_type' => 'IT Consult',
            'message_type' => 'General Inquiry',
            'message' => 'This is a sample message',
        ];

        $response = $this->postJson('/api/v1/merchant-contact', $data);

        $response
            ->assertOk()
            ->assertJson(['message' => 'Merchant contact information submitted successfully']);

        $this->assertDatabaseHas('merchant_contacts', [
            'name' => 'Alex Koh',
            'email' => 'alex@funhub.my',
            'tel_no' => '182036794',
            'company_name' => 'Funhub TV',
            'business_type' => 'IT Consult',
            'message_type' => 'General Inquiry',
            'message' => 'This is a sample message',
        ]);
    }

}