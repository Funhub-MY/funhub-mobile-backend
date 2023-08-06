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
        ]);
        $response->assertStatus(200)
            ->assertJsonStructure([
                'message',
                'offer'
            ]);

        // check current user latest point ledger is debit or not.
        $user_point_ledgers = $this->loggedInUser->pointLedgers()->orderBy('id','desc')->first();
        $this->assertEquals(1, $user_point_ledgers->debit);
    }
}
