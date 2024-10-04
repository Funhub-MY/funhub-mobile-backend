<?php

use App\Models\User;
use App\Models\UserCard;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class UserCardTest extends TestCase
{
    use RefreshDatabase;

    protected $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        Sanctum::actingAs($this->user, ['*']);
    }

    public function test_get_user_cards()
    {
        // Create some test cards for the user
        UserCard::factory()->count(3)->create(['user_id' => $this->user->id]);

        $response = $this->getJson('/api/v1/user/settings/cards');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'cards' => [
                    '*' => [
                        'id',
                        'user_id',
                        'card_type',
                        'card_last_four',
                        'card_holder_name',
                        'card_expiry_month',
                        'card_expiry_year',
                        'is_default',
                        'is_expired',
                        'created_at',
                        'updated_at',
                    ]
                ]
            ]);

        $this->assertCount(3, $response->json('cards'));
    }

    public function test_remove_user_card()
    {
        // Create a test card for the user
        $card = UserCard::factory()->create(['user_id' => $this->user->id]);

        $response = $this->postJson('/api/v1/user/settings/card/remove', [
            'card_id' => $card->id
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'message' => __('messages.success.user_settings_controller.Card_removed')
            ]);

        $this->assertDatabaseMissing('user_cards', ['id' => $card->id]);
    }

    public function test_set_card_as_default()
    {
        // Create two test cards for the user
        $card1 = UserCard::factory()->create(['user_id' => $this->user->id, 'is_default' => false]);
        $card2 = UserCard::factory()->create(['user_id' => $this->user->id, 'is_default' => true]);

        $response = $this->postJson('/api/v1/user/settings/card/set-as-default', [
            'card_id' => $card1->id
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'message' => __('messages.success.user_settings_controller.Card_set_as_default')
            ]);

        $this->assertDatabaseHas('user_cards', [
            'id' => $card1->id,
            'is_default' => 1
        ]);

        $this->assertDatabaseHas('user_cards', [
            'id' => $card2->id,
            'is_default' => 0
        ]);
    }
}
