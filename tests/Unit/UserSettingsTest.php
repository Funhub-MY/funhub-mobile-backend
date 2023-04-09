<?php
namespace Tests\Unit;

use Tests\TestCase;
use App\Models\UserSetting;
use App\Models\User;
use App\Models\ArticleCategory;
use App\Models\Country;
use App\Models\State;
use Laravel\Sanctum\Sanctum;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;

class UserSettingsTest extends TestCase
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
     * Test assign categories to a user
     * /api/v1/user/settings/article_categories
     */
    public function testAssignCategoriesToAUser()
    {
        // create article category first
        $articleCategory = ArticleCategory::factory()->count(10)->create();

        // assign categories to a user
        $response = $this->postJson('/api/v1/user/settings/article_categories', [
            'category_ids' => $articleCategory->pluck('id')->toArray()
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'message'
            ]);
    }

    /**
     * Test Upload user avatar
     * /api/v1/user/settings/avatar/upload
     */
    public function testUploadUserAvatar()
    {
        // upload user avatar
        $response = $this->postJson('/api/v1/user/settings/avatar/upload', [
            'avatar' => UploadedFile::fake()->image('avatar.jpg')
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'message', 'avatar', 'avatar_thumb'
            ]);

        // assert database of user avatar column is populated
        $this->assertDatabaseHas('users', [
            'id' => $this->user->id,
            'avatar' => $response->json('avatar_id'),
        ]);
    }

    /**
     * Test Save Email
     * /api/v1/user/settings/email
     */
    public function testSaveEmail()
    {
        // save email
        $response = $this->postJson('/api/v1/user/settings/email', [
            'email' => 'test123@gmail.com'
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'message'
            ]);

        // assert database of user email column is populated
        $this->assertDatabaseHas('users', [
            'id' => $this->user->id,
            'email' => 'test123@gmail.com',
        ]);
    }

    /**
     * Test Save Name
     * /api/v1/user/settings/name
     */
    public function testSaveName()
    {
        // save name
        $response = $this->postJson('/api/v1/user/settings/name', [
            'name' => 'test123'
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'message'
            ]);

        // assert database of user name column is populated
        $this->assertDatabaseHas('users', [
            'id' => $this->user->id,
            'name' => 'test123',
        ]);
    }

    /**
     * Test Save Bio
     * /api/v1/user/settings/bio
     */
    public function testSaveBio()
    {
        $bio = fake()->paragraph(3);
        // save bio
        $response = $this->postJson('/api/v1/user/settings/bio', [
            'bio' => $bio
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'message'
            ]);

        // assert database of user bio column is populated
        $this->assertDatabaseHas('users', [
            'id' => $this->user->id,
            'bio' => $bio,
        ]);
    }

    /**
     * Test Save Date of Birth
     * /api/v1/user/settings/dob 
     */
    public function testSaveDob()
    {
        $date = [
            'year' => 1990,
            'month' => fake()->date('m'),
            'day' => fake()->date('d'),
        ];
        // save dob
        $response = $this->postJson('/api/v1/user/settings/dob', [
            'year' =>  (int) $date['year'],
            'month' => (int) $date['month'],
            'day' => (int) $date['day'],
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'message'
            ]);

        // assert database of user dob column is populated
        $this->assertDatabaseHas('users', [
            'id' => $this->user->id,
            'dob' => $date['year'].'-'.$date['month'].'-'.$date['day'],
        ]);
    }

    /**
     * Test Save Gender
     * /api/v1/user/settings/gender
     */
    public function testSaveGender()
    {
        $response = $this->postJson('/api/v1/user/settings/gender', [
            'gender' => 'male'
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'message'
            ]);
    
        // assert database
        $this->assertDatabaseHas('users', [
            'id' => $this->user->id,
            'gender' => 'male'
        ]);
    }

    /**
     * Test Save Location
     * /api/v1/user/settings/location
     */
    public function testSaveLocation()
    {
        // seed countries
        $this->seed('CountriesTableSeeder');

        // seed states
        $this->seed('StatesTableSeeder');

        // get Malaysia country id
        $country = \App\Models\Country::where('code', 'MY')->first();
        $state = \App\Models\State::where('country_id', $country->id)->first();
        // post to save location
        $response = $this->postJson('/api/v1/user/settings/location', [
            'country_id' => $country->id,
            'state_id' => $state->id,
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'message'
            ]);

        // assert database
        $this->assertDatabaseHas('users', [
            'id' => $this->user->id,
            'country_id' => $country->id,
            'state_id' => $state->id,
        ]);
    }
}
