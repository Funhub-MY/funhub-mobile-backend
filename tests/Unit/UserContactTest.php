<?php

namespace Tests\Unit;

namespace Tests\Unit;

use App\Models\ArticleCategory;
use App\Mail\EmailVerification;
use Tests\TestCase;
use App\Models\User;
use App\Models\Country;
use App\Models\State;
use App\Models\UserContact;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Laravel\Sanctum\Sanctum;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;

class UserContactTest extends TestCase
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

    // test import contacts
    public function testImportContacts()
    {
        $contacts = [
            [
                'country_code' => '60',
                'phone_no' => '123456788',
                'name' => 'John 88',
            ],
            [
                'country_code' => '60',
                'phone_no' => '123456799',
                'name' => 'John 99',
            ],
            [
                'country_code' => '60',
                'phone_no' => '123456700',
                'name' => 'John 00',
            ],
        ];

        // create existing users first for first two contacts
        User::factory()->create([
            'phone_country_code' => $contacts[0]['country_code'],
            'phone_no' => $contacts[0]['phone_no'],
            'status' => User::STATUS_ACTIVE,
        ]);

        User::factory()->create([
            'phone_country_code' => $contacts[1]['country_code'],
            'phone_no' => $contacts[1]['phone_no'],
            'status' => User::STATUS_ACTIVE,
        ]);

        $response = $this->postJson('/api/v1/user/import-contacts', [
            'contacts' => $contacts,
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'message' => 'Contacts imported successfully',
        ]);

        // check db for imported contacts should have 3 records, but 2 records should have related_user_id matched
        // query for imported contacts
        $contacts = UserContact::all();

        // check all contacts should be 3
        $this->assertEquals(3, $contacts->count());

        // check 2 with related_user_id
        $this->assertEquals(2, $contacts->whereNotNull('related_user_id')->count());
    }
}
