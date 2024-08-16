<?php

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

    // test import contacts with invalid data
    public function testImportContactsInvalidData()
    {
        $contacts = [
            [
                'country_code' => '60',
                'phone_no' => '',
                'name' => 'John 88',
            ],
            [
                'country_code' => '',
                'phone_no' => '123456799',
                'name' => 'John 99',
            ],
        ];

        $response = $this->postJson('/api/v1/user/import-contacts', [
            'contacts' => $contacts,
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['contacts.0.phone_no', 'contacts.1.country_code']);
    }

    // test import contacts with no contacts data
    public function testImportContactsNoContactsData()
    {
        $response = $this->postJson('/api/v1/user/import-contacts', []);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['contacts']);
    }

    // test get contacts not yet followed
    public function testGetContactsNotYetFollowed()
    {
        // create users first
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();
        $user3 = User::factory()->create();

        // create user contacts
        UserContact::factory()->create([
            'phone_country_code' => $user1->phone_country_code,
            'phone_no' => $user1->phone_no,
            'imported_by_id' => $this->user->id,
            'related_user_id' => $user1->id,
        ]);

        UserContact::factory()->create([
            'phone_country_code' => $user2->phone_country_code,
            'phone_no' => $user2->phone_no,
            'imported_by_id' => $this->user->id,
            'related_user_id' => $user2->id,
        ]);

        UserContact::factory()->create([
            'phone_country_code' => $user3->phone_country_code,
            'phone_no' => $user3->phone_no,
            'imported_by_id' => $this->user->id,
            'related_user_id' => $user3->id,
        ]);

        // let user follow user1
        $this->user->followings()->attach($user1->id);

        $response = $this->getJson('/api/v1/user/contacts-friends');

        $response->assertStatus(200);
        $response->assertJsonCount(2, 'users');
        $response->assertJsonPath('users.0.id', $user2->id);
        $response->assertJsonPath('users.1.id', $user3->id);
    }

    // test get contacts not yet followed but no contacts found
    public function testGetContactsNotYetFollowedNoContacts()
    {
        $response = $this->getJson('/api/v1/user/contacts-friends');

        $response->assertStatus(200);
        $response->assertJson([
            'message' => 'No friends found',
        ]);
    }
}
