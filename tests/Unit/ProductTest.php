<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Models\Product;
use App\Models\Reward;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

class ProductTest extends TestCase
{
    use RefreshDatabase;

    protected $user, $reward, $product;

    public function setUp(): void
    {
        parent::setUp();
        $this->refreshDatabase();

        $this->user = User::factory()->create();
        Sanctum::actingAs($this->user,['*']);

        $this->product = Product::factory()->create();

        // attach a reward to a product
        $this->reward = Reward::create([
            'name' => 'Funbox',
            'description' => 'A box of fun',
            'points' => 1,
            'user_id' => $this->user->id
        ]);
    }

    public function testPostCheckoutProduct()
    {
        $response = $this->postJson('/api/v1/products/checkout', [
            'product_id' => $this->product->id,
            'quantity' => 1,
            'payment_method' => 'fiat',
            'fiat_payment_method' => 'fpx'
        ]);

        // assert status is 200
        $response->assertStatus(200);

        // assert gateway data has all required information
        $response->assertJsonStructure([
            'message',
            'transaction_no',
            'gateway_data' => [
                'url',
                'formData' => [
                    'secureHash',
                    'mid',
                    'invno',
                    'amt',
                    'desc',
                    'postURL',
                    'phone',
                    'email',
                    'param'
                ]
            ]
        ]);

        // assert a PENDING transaction is created
        $this->assertDatabaseHas('transactions', [
            'transactionable_id' => $this->product->id,
            'transactionable_type' => Product::class,
            'user_id' => $this->user->id,
            'status' => Transaction::STATUS_PENDING,
            'gateway_transaction_id' => 'N/A'
        ]);
    }
}
