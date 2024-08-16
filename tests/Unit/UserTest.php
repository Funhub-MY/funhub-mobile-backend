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

    /**
     * Test get public user details
     */
    public function testGetPublicUser() {
        $user = User::factory()->create();
        Sanctum::actingAs($user,['*']);

            $response = $this->getJson("/api/v1/public/user/{$user->id}");
            //dd($response->json());

            $response->assertStatus(200);

            $response->assertJsonStructure([
                'data' => [
                    'id',
                    'name',
                    'username',
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
                ],
            ]);
    }

    /**
     * Test update name
     */
    public function testPostUpdateUserDetailsName() {

        $response = $this->postJson("/api/v1/user", [
            'update_type' => 'name',
            'name' => 'John Doe',
        ]);

        $response->assertStatus(200);

        $response->assertJsonStructure([
            'message',
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
        ]);

        $this->assertDatabaseHas('users', [
            'id' => $this->user->id,
            'name' => 'John Doe',
        ]);
    }

    /**
     * Test update username
     */
    public function testPostUpdateUserDetailsUsername() {

        $response = $this->postJson("/api/v1/user", [
            'update_type' => 'username',
            'username' => 'JohnDoe',
        ]);

        $response->assertStatus(200);

        $response->assertJsonStructure([
            'message',
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
        ]);

        $this->assertDatabaseHas('users', [
            'id' => $this->user->id,
            'username' => 'JohnDoe',
        ]);
    }

    /**
     * Test update bio
     */
    public function testPostUpdateUserDetailsBio() {
        $bio = fake()->paragraph(3);

        $response = $this->postJson("/api/v1/user", [
            'update_type' => 'bio',
            'bio' => $bio,
        ]);

        $response->assertStatus(200);

        $response->assertJsonStructure([
            'message',
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
        ]);

        $this->assertDatabaseHas('users', [
            'id' => $this->user->id,
            'bio' => $bio,
        ]);
    }

    /**
     * Test update job title
     */
    public function testPostUpdateUserDetailsJobTitle() {
        $response = $this->postJson("/api/v1/user", [
            'update_type' => 'job_title',
            'job_title' => 'Software Engineer',
        ]);

        $response->assertStatus(200);

        $response->assertJsonStructure([
            'message',
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
        ]);

        $this->assertDatabaseHas('users', [
            'id' => $this->user->id,
            'job_title' => 'Software Engineer'
        ]);
    }

    /**
     * Test update date of birth
     */
    public function testPostUpdateUserDetailsDob() {
        $date = [
            'year' => 1990,
            'month' => fake()->date('m'),
            'day' => fake()->date('d'),
        ];

        $response = $this->postJson("/api/v1/user", [
            'update_type' => 'dob',
            'year' =>  (int) $date['year'],
            'month' => (int) $date['month'],
            'day' => (int) $date['day'],
        ]);

        $response->assertStatus(200);

        $response->assertJsonStructure([
            'message',
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
        ]);

        $this->assertDatabaseHas('users', [
            'id' => $this->user->id,
            'dob' => $date['year'].'-'.$date['month'].'-'.$date['day'],
        ]);
    }

    /**
     * Test update gender
     */
    public function testPostUpdateUserDetailsGender() {
        $response = $this->postJson("/api/v1/user", [
            'update_type' => 'gender',
            'gender' => 'male',
        ]);

        $response->assertStatus(200);

        $response->assertJsonStructure([
            'message',
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
        ]);

        $this->assertDatabaseHas('users', [
            'id' => $this->user->id,
            'gender' => 'male'
        ]);
    }

    /**
     * Test update location
     */
    public function testPostUpdateUserDetailsLocation() {
        $this->seed('CountriesTableSeeder');
        $this->seed('StatesTableSeeder');

        $country = Country::where('code', 'MY')->first();
        $state = State::where('country_id', $country->id)->first();

        $response = $this->postJson("/api/v1/user", [
            'update_type' => 'location',
            'country_id' => $country->id,
            'state_id' => $state->id,
        ]);

        $response->assertStatus(200);

        $response->assertJsonStructure([
            'message',
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
        ]);

        $this->assertDatabaseHas('users', [
            'id' => $this->user->id,
            'country_id' => $country->id,
            'state_id' => $state->id,
        ]);
    }

    /**
     * Test update avatar
     */
    public function testPostUpdateUserDetailsAvatar() {


        $response = $this->postJson("/api/v1/user", [
            'update_type' => 'avatar',
            'avatar' => UploadedFile::fake()->image('avatar.jpg')
        ]);

        $response->assertStatus(200);

        $response->assertJsonStructure([
            'message',
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
        ]);

        $this->assertDatabaseHas('users', [
            'id' => $this->user->id,
            'avatar' => $response->json('avatar_id'),
        ]);
    }

    /**
     * Test update cover
     */
    public function testPostUpdateUserDetailsCover() {


        $response = $this->postJson("/api/v1/user", [
            'update_type' => 'cover',
            'cover' => UploadedFile::fake()->image('avatar.jpg')
        ]);

        $response->assertStatus(200);

        $response->assertJsonStructure([
            'message',
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
        ]);

        $this->assertDatabaseHas('users', [
            'id' => $this->user->id,
            'cover' => $response->json('cover_id'),
        ]);
    }

    /**
     * Test update cover
     */
    public function testPostArticleCategoriesInterests() {
        $articleCategory = ArticleCategory::factory()->count(10)->create();

        $response = $this->postJson("/api/v1/user", [
            'update_type' => 'article_categories',
            'category_ids' => $articleCategory->pluck('id')->toArray()
        ]);

        $response->assertStatus(200);

        $response->assertJsonStructure([
            'message',
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
        ]);

        $this->assertDatabaseHas('users', [
            'id' => $this->user->id,
            'cover' => $response->json('cover_id'),
        ]);
    }

    /**
     * Test update password
     */
    public function testUpdatePassword() {

        $this->user = User::factory()->create([
            'password' => Hash::make('password'),
        ]);
        Sanctum::actingAs($this->user,['*']);

        $response = $this ->postJson("/api/v1/user/password", [
            'old_password' => 'password',
            'new_password' => 'abcd1234',
            'new_password_confirmation' => 'abcd1234',
        ]);

        $response->assertStatus(200);

        $response->assertJsonStructure([
            'message',
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
        ]);

        $updatedUser = User::find($this->user->id);

        $this->assertTrue(Hash::check('abcd1234', $updatedUser->password));
    }


    /**
     * Test update email
     */
    public function testUpdateEmail() {

        $response = $this->postJson("/api/v1/user/email", [
            'email' => 'test123@gmail.com'
        ]);

        $response->assertStatus(200);

        $response->assertJsonStructure([
            'message',
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
        ]);

        $this->assertDatabaseHas('users', [
            'id' => $this->user->id,
            'email' => 'test123@gmail.com',
        ]);

    }

    /**
     * Test post tutorial progress
     */
    public function testPostTutorialProgress()
    {
        // Get the tutorial steps from the config
        $tutorialSteps = config('app.tutorial_steps');

        // Choose a random step from the tutorial steps
        $randomStep = 'first_time_visit_any_store';

        $response = $this->postJson("/api/v1/user/tutorial-progress", [
            'tutorial_step' => $randomStep
        ]);

        $response->assertStatus(200);

        $response->assertJsonStructure([
            'message',
            'tutorial_step',
            'completed_at',
        ]);

        $response->assertJson([
            'message' => __('messages.success.user_controller.Tutorial_progress_saved'),
            'tutorial_step' => $randomStep,
        ]);

        // Test with an invalid step
        $response = $this->postJson("/api/v1/user/tutorial-progress", [
            'tutorial_step' => 'invalid_step'
        ]);

        $response->assertStatus(422);
    }

}
