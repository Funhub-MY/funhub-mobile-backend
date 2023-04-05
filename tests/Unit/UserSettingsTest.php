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
}
