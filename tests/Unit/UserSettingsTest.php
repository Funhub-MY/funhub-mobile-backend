<?php
namespace Tests\Unit;

use Tests\TestCase;
use App\Models\UserSetting;
use App\Models\User;
use App\Models\ArticleCategory;
use Laravel\Sanctum\Sanctum;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;

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
     * /api/v1/user-settings/article_categories
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
     * /api/v1/user-settings/avatar/upload
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
     * /api/v1/user-settings/email
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
     * /api/v1/user-settings/name
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
}
