<?php

namespace Tests\Unit;

use App\Models\MerchantCategory;
use App\Models\PointLedger;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;
use App\Models\Merchant;
use App\Models\MerchantOffer;
use App\Models\Store;
use App\Models\Transaction;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

class MerchantOfferTest extends TestCase
{
    use RefreshDatabase;
    protected $user, $merchant, $store, $merchant_offer, $merchant_category, $loggedInUser;
    public function setUp(): void
    {
        parent::setUp();
        $this->refreshDatabase();
        // create user first to attach the foreign keys for each model below.
        // $this->user is used to test on merchant offer. Act as a merchant.
        $this->user = User::factory()->create();
        // $this->loggedInUser is to use at claims test. Act as normal user.
        $this->loggedInUser = User::factory()->create();
        // as 1-to-1 relationship, we can use 'for' here to tell the factory which user is belongsTo when creating the model below.
        $this->merchant = Merchant::factory()->for($this->user)->create();
        $this->store = Store::factory()->for($this->user)->create();
        // we can chain double for as well.
        $this->merchant_offer = MerchantOffer::factory()->count(5)->for($this->merchant->user)->create();
        $this->merchant_category = MerchantCategory::factory()->for($this->merchant->user)->create();
        // attach offer with category
        foreach($this->merchant_offer as $offer) {
            $offer->categories()->attach($this->merchant_category);
        }
        Sanctum::actingAs($this->user, ['*']);
        Sanctum::actingAs($this->loggedInUser, ['*']);
    }

    /**
     * A basic unit test example.
     *
     * @return void
     */
    public function testAvailableAndComingSoonOffers()
    {
        // get 1 of the Merchant offer, set it as coming soon
        $merchant_offer = $this->merchant_offer->random(1)->first();
        // add 1 day to indicate this offer is 'coming soon' type of offer.
        $merchant_offer->available_at = Carbon::parse($merchant_offer->available_at)->subDays(10);
        $merchant_offer->save();
        // api call to get merchant offer.
        $response = $this->getJson('/api/v1/merchant/offers');
        $response->assertStatus(200)
                ->assertJsonStructure([
                    'data',
                    'meta' // to check if there is meta here it to get the total item that return by paginator. If meta exists, mean paginated as well.
                ]);
        $total = $response->json('meta.total');
        // as above created 5 matching offers, we are expecting 5 items in the responses as well.
        $this->assertEquals(5, $total);
    }

    public function testGetOfferByCategories()
    {
        // mock api with hard-coded category, as when setup we only set 1 category.
        $response = $this->getJson('/api/v1/merchant/offers?category_ids='.implode(',' ,$this->merchant_category->pluck('id')->toArray()));
        $response->assertStatus(200)
                ->assertJsonStructure([
                    'data',
                    'meta',
                ]);
        $total = $response->json('meta.total');
        $this->assertEquals(5, $total);

        // then create new category and offer
        $merchant_category = MerchantCategory::factory()->for($this->merchant->user)->create();
        $merchant_offer = MerchantOffer::factory()->for($this->merchant->user)->create();
        $merchant_offer->categories()->attach($merchant_category);
        // need look all $merchant_category again.
        $this->merchant_category = MerchantCategory::orderBy('id', 'DESC');
        // then fetch post another category
        $response = $this->getJson('/api/v1/merchant/offers?category_ids='.implode(',' , $this->merchant_category->pluck('id')->toArray()));
        $response->assertStatus(200)
            ->assertJsonStructure([
                'data',
                'meta',
            ]);
        $total = $response->json('meta.total');
        $this->assertEquals(6, $total);

        // test fetch category_ids 2 only
        $response = $this->getJson('/api/v1/merchant/offers?category_ids='.$merchant_category->id);
        $response->assertStatus(200)
            ->assertJsonStructure([
                'data',
                'meta',
            ]);
        $total = $response->json('meta.total');
        $this->assertEquals(1, $total);

    }


    public function testClaimExpiredOfferByLoggedInUser()
    {
        // as in previous test, we inserted point balance for the logged in user, we dont need to do any insertion anymore.
        // get one merchant offer, set available_at and until as NOT VALID.
        $merchant_offer = $this->merchant_offer->random(1)->first();
        $merchant_offer->available_at = now()->subDay();
        $merchant_offer->available_until = now()->subDay();
        $merchant_offer->save();

        $response = $this->postJson('/api/v1/merchant/offers/claim', [
            'offer_id' => $merchant_offer->id,
            'quantity' => 1,
            'payment_method' => 'points'
        ]);
        // expired should return 422 with message.
        $response->assertStatus(422)
            ->assertJsonStructure([
                'message',
            ]);
    }

    public function testClaimOfferWithNoBalanceByLoggedInUser()
    {
        // as in previous test, we inserted point balance for the logged in user, we dont need to do any insertion anymore.
        // get one merchant offer, set available_at and until as VALID.
        $merchant_offer = $this->merchant_offer->random(1)->first();
        $merchant_offer->available_at = now();
        $merchant_offer->available_until = now()->addDay();
        $merchant_offer->save();

        $response = $this->postJson('/api/v1/merchant/offers/claim', [
            'offer_id' => $merchant_offer->id,
            'quantity' => 1,
            'payment_method' => 'points'
        ]);
        // expired should return 422 with message.
        $response->assertStatus(422)
            ->assertJsonStructure([
                'message',
            ]);
    }

    public function testClaimOfferByLoggedInUser()
    {
        // this test put after the negative flow as it needed to topup points and negative flow does not needed.
        // insert points for users first.
        // we use $this->loggedInUser here instead of $this->user.
        PointLedger::create([
            'user_id' => $this->loggedInUser->id,
            'pointable_type' => get_class($this->loggedInUser),
            'pointable_id' => $this->loggedInUser->id,
            'title' => 'First topup simulation',
            'amount' => 1000,
            'credit' => 1,
            'debit' => 0,
            'balance' => 1000,
            'remarks' => 'Simulation topup points for user.',
            'created_at' => now(),
            'updated_at' => now()
        ]);
        // get one merchant offer, set available_at as today, until as tomorrow to ensure the offer is valid.
        $merchant_offer = $this->merchant_offer->random(1)->first();
        $merchant_offer->available_at = now();
        $merchant_offer->available_until = now()->addDay();
        $merchant_offer->save();

        $response = $this->postJson('/api/v1/merchant/offers/claim', [
            'offer_id' => $merchant_offer->id,
            'quantity' => 1,
            'payment_method' => 'points'
        ]);
        $response->assertStatus(200)
            ->assertJsonStructure([
                'message',
                'offer'
            ]);

        // check current user latest point ledger is debit or not.
        $user_point_ledgers = $this->loggedInUser->pointLedgers()->orderBy('id','desc')->first();
        $this->assertEquals(1, $user_point_ledgers->debit);

        // check my offers are there or not in /merchant/offers/my_claimed_offers
        $response = $this->getJson('/api/v1/merchant/offers/my_claimed_offers');
        $response->assertStatus(200)
            ->assertJsonStructure([
                'data',
                'meta'
            ]);

        // check each of my response.data matches $merchant_offer->id
        foreach ($response->json('data') as $data) {
            $this->assertEquals($merchant_offer->id, $data['id']);
        }

        // check count is 1
        $this->assertEquals(1, $response->json('meta.total'));
    }

    /**
     * Test Claim Offer with Fiat(Cash) by Logged In User
     *
     * Fiat will need have mpay gateway env to test
     */
    public function testClaimOfferFiatByLoggedInUser()
    {
        // create a merchant offer with fiat
        $offer = MerchantOffer::factory()->for($this->merchant->user)->create([
            'fiat_price' => 150,
            'discounted_fiat_price' => 120,
            'currency' => 'MYR',
            'quantity' => 10
        ]);

        // user claims this offer for 5 units first
        $response = $this->postJson('/api/v1/merchant/offers/claim', [
            'offer_id' => $offer->id,
            'quantity' => 5,
            'payment_method' => 'fiat',
            'fiat_payment_method' => 'fpx'
        ]);

        // expect 200 response
        $response->assertStatus(200)
            ->assertJsonStructure([
                'message',
                'gateway_data'
            ]);

        // check gateway data as follow
        $this->assertArrayHasKey('url', $response->json('gateway_data'));
        $this->assertArrayHasKey('formData', $response->json('gateway_data'));
        
        // check db if transaction is created
        $this->assertDatabaseHas('transactions', [
            'transaction_no' => $response->json('gateway_data')['formData']['invno'],
            'user_id' => $this->loggedInUser->id,
            'amount' => $offer->discounted_fiat_price * 5,
            'gateway' => 'mpay',
            'status' => Transaction::STATUS_PENDING,
            'gateway_transaction_id' => 'N/A',
            'payment_method' => 'fpx'
        ]);

        // check if offer->claims() has user and correct quantity and status is MerchantOffer::AWAIT_PAYMENT
        $this->assertDatabaseHas('merchant_offer_user', [
            'user_id' => $this->loggedInUser->id,
            'merchant_offer_id' => $offer->id,
            'quantity' => 5,
            'status' => MerchantOffer::CLAIM_AWAIT_PAYMENT
        ]);

        // check if current merchantoffer is already deducted 5
        $this->assertEquals(5, $offer->fresh()->quantity);
    }

    /**
     * Test Redeem Offer by Logged In User
     */
    // public function testRedeemClaimedOfferByLoggedInUser()
    // {
    //     PointLedger::create([
    //         'user_id' => $this->loggedInUser->id,
    //         'pointable_type' => get_class($this->loggedInUser),
    //         'pointable_id' => $this->loggedInUser->id,
    //         'title' => 'First topup simulation',
    //         'amount' => 1000,
    //         'credit' => 1,
    //         'debit' => 0,
    //         'balance' => 1000,
    //         'remarks' => 'Simulation topup points for user.',
    //         'created_at' => now(),
    //         'updated_at' => now()
    //     ]);
    //     // get one merchant offer, set available_at as today, until as tomorrow to ensure the offer is valid.
    //     $merchant_offer = $this->merchant_offer->random(1)->first();
    //     $merchant_offer->available_at = now();
    //     $merchant_offer->available_until = now()->addDay();
    //     $merchant_offer->save();

    //     dd($merchant_offer);


    //     // change merchant_offer->merchant->redeem_code
    //     Merchant::where('id', $merchant_offer->merchant->id)->update([
    //         'redeem_code' => '334455'
    //     ]);

    //     $response = $this->postJson('/api/v1/merchant/offers/claim', [
    //         'offer_id' => $merchant_offer->id,
    //         'quantity' => 1,
    //         'payment_method' => 'points'
    //     ]);
    //     $response->assertStatus(200)
    //         ->assertJsonStructure([
    //             'message',
    //             'offer'
    //         ]);

    //     // check current user latest point ledger is debit or not.
    //     $user_point_ledgers = $this->loggedInUser->pointLedgers()->orderBy('id','desc')->first();
    //     $this->assertEquals(1, $user_point_ledgers->debit);

    //     // now proceed to redeem
    //     $response = $this->postJson('/api/v1/merchant/offers/redeem', [
    //         'offer_id' => $merchant_offer->id,
    //         'redeem_code' => '334455',
    //         'quantity' => 5 // over my claimed quantity
    //     ]);

    //     // expect 422 with json message "You do not have enough to redeem"
    //     $response->assertStatus(422)
    //         ->assertJson([
    //             'message' => 'You do not have enough to redeem'
    //         ]);

    //     // now proceed to redeem again with correct quantity
    //     $response = $this->postJson('/api/v1/merchant/offers/redeem', [
    //         'offer_id' => $merchant_offer->id,
    //         'redeem_code' => '334455',
    //         'quantity' => 1
    //     ]);


    //     // expect 200 with json message "Redeemed Successfully"
    //     $response->assertStatus(200)
    //         ->assertJson([
    //             'message' => 'Redeemed Successfully'
    //         ]);

    //     // check db if merchant_offer_claims_redemptions has this user, offer id and quantity
    //     $this->assertDatabaseHas('merchant_offer_claims_redemptions', [
    //         'user_id' => $this->loggedInUser->id,
    //         'merchant_offer_id' => $merchant_offer->id,
    //         'quantity' => 1
    //     ]);

    //     // attempt to redeem again, expect 422 with json message "You have already redeemed this offer"
    //     $response = $this->postJson('/api/v1/merchant/offers/redeem', [
    //         'offer_id' => $merchant_offer->id,
    //         'redeem_code' => '334455',
    //         'quantity' => 1
    //     ]);

    //     $response->assertStatus(422)
    //         ->assertJson([
    //             'message' => 'You do not have enough to redeem'
    //         ]);
    // }
}
