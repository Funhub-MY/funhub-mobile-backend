<?php

namespace App\Http\Controllers\Api;

use App\Events\MerchantOfferClaimed;
use App\Http\Controllers\Controller;
use App\Http\Resources\MerchantOfferResource;
use App\Models\Interaction;
use App\Models\MerchantOffer;
use App\Services\Mpay;
use App\Services\PointService;
use App\Services\TransactionService;
use App\Traits\QueryBuilderTrait;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class MerchantOfferController extends Controller
{
    use QueryBuilderTrait;

    protected $pointService, $transactionService;

    public function __construct()
    {
        $this->pointService = new PointService();
        $this->transactionService = new TransactionService();
    }

    /**
     * Get Offers
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     *
     * @group Merchant
     * @subgroup Merchant Offers
     *
     * @bodyParam category_ids array optional Merchant Category Ids to Filter. Example: [1, 2, 3]
     * @bodyParam city string optional Filter by City. Example: Subang Jaya
     * @bodyParam lat float optional Filter by Lat of User (must provide lng). Example: 3.123456
     * @bodyParam lng float optional Filter by Lng of User (must provide lat). Example: 101.123456
     * @bodyParam radius integer optional Filter by Radius (in meters) if provided lat, lng. Example: 10000
     * @bodyParam location_id integer optional Filter by Location Id. Example: 1
     * @bodyParam filter string Column to Filter. Example: Filterable columns are: id, name, description, available_at, available_until, sku
     * @bodyParam filter_value string Value to Filter. Example: Filterable values are: 1, 2, 3, 4, 5, 6, 7, 8, 9, 10
     * @bodyParam sort string Column to Sort. Example: Sortable columns are: id, name, description, available_at, available_until, sku, created_at, updated_at
     * @bodyParam order string Direction to Sort. Example: Sortable directions are: asc, desc
     * @bodyParam limit integer Per Page Limit Override. Example: 10
     * @bodyParam offset integer Offset Override. Example: 0
     *
     * @response scenario=success {
     *  "data": [],
     *  "links": {},
     *  "meta": {
     *     "current_page": 1,
     *   }
     * }
     *
     */
    public function index(Request $request)
    {
        // ensure only published offers
        $query = MerchantOffer::query()
            ->published()
            ->with('merchant', 'merchant.user', 'categories', 'store', 'claims', 'user');

        // category_ids filter
        if ($request->has('category_ids')) {
            // explode categories ids
            $category_ids = explode(',', $request->category_ids);
            if (count($category_ids) > 0) {
                $query->whereHas('categories', function ($q) use ($category_ids) {
                    $q->whereIn('merchant_categories.id', $category_ids);
                });
            }
        }
        // ensure offer is valid/coming soon
        // $query->where(function ($query) {
        //     $query->where('available_at', '<=', now())
        //         ->where('available_until', '>=', now())
        //         ->orWhere('available_at', '>=', now());
        // });
        // order by latest first if no query sort order
        if (!$request->has('sort')) {
            $query->orderBy('created_at', 'desc');
        }

        // get articles by city
        if ($request->has('city')) {
            $query->whereHas('location', function ($query) use ($request) {
                $query->where('city', 'like', '%' . $request->city . '%');
            });
        }

        // get articles by lat, lng
        if ($request->has('lat') && $request->has('lng')) {
            $radius = $request->has('radius') ? $request->radius : 10000; // 10km default
            // get article where article->location lat,lng is within the radius
            $query->whereHas('location', function ($query) use ($request, $radius) {
                $query->selectRaw('( 6371 * acos( cos( radians(?) ) *
                    cos( radians( lat ) )
                    * cos( radians( lng ) - radians(?)
                    ) + sin( radians(?) ) *
                    sin( radians( lat ) ) )
                    ) AS distance', [$request->lat, $request->lng, $request->lat])
                    ->havingRaw("distance < ?", [$radius]);
            });
        }

        // location id
        if ($request->has('location_id')) {
            $query->whereHas('location', function ($query) use ($request) {
                $query->where('locations.id', $request->location_id);
            });
        }

        $this->buildQuery($query, $request);
        $data = $query->paginate(config('app.paginate_per_page'));
        return MerchantOfferResource::collection($data);
    }

    /**
     * Get My Merchant Offers (Logged in User)
     *
     * @return \Illuminate\Http\JsonResponse
     * 
     * @group Merchant
     * @subgroup Merchant Offers
     * @response scenario=success {
     * "data": []
     * }
     */
    public function getMyMerchantOffers()
    {
        // get merchant offers claimed by user
        $merchantOffers = MerchantOffer::whereHas('claims', function ($query) {
            $query->where('user_id', auth()->user()->id);
        })->paginate(config('app.paginate_per_page'));

        return MerchantOfferResource::collection($merchantOffers);
    }

    /**
     * Get Offer By ID
     *
     * @param MerchantOffer $merchantOffer
     * @return \Illuminate\Http\JsonResponse
     *
     * @group Merchant
     * @subgroup Merchant Offers
     * @queryParam offer_id integer required Offer ID. Example: 1
     * @response scenario=success {
     * "offer": {}
     * }
     *
     */
    public function show($id)
    {
        $offer = MerchantOffer::where('id', $id)->first();
        return new MerchantOfferResource($offer);
    }

    /**
     * Claim Offer
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     *
     * @group Merchant
     * @subgroup Merchant Offers
     * @bodyParam offer_id integer required Offer ID. Example: 1
     * @bodyParam quantity integer required Quantity. Example: 1
     * @bodyParam payment_method string required Payment Method. Example: points/fiat
     * @bodyParam fiat_payment_method string required_if:payment_method,fiat Payment Method. Example: fpx/card
     * @response scenario=success {
     * "message": "Offer Claimed"
     * }
     * @response scenario=insufficient_point_balance {
     * "message": "Insufficient Point Balance"
     * }
     * @response scenario=fiat_payment_mode {
     * "message": "Redirect to gateway",
     * "gateway_data": [
     *      'url' => $this->url .'/payment/eCommerce',
     *       'secureHash' => $this->generateHashForRequest($this->mid, $invoice_no, $amount),
     *       'mid' => $this->mid,
     *       'invno' => $invoice_no,
     *       'capture_amt' => $amount,
     *       'desc' => $desc,
     *       'postURL' => $redirectUrl,
     *       'phone' => $phoneNo,
     *       'email' => $email,
     *       'param' => $param,
     *       'authorize' => 'authorize'
     *   ];
     * }
     * @response scenario=offer_no_longer_valid {
     * "message": "Offer is no longer valid"
     * }
     */
    public function postClaimOffer(Request $request)
    {
        $request->validate([
            'offer_id' => 'required|integer|exists:merchant_offers,id',
            'payment_method' => 'required|in:points,fiat',
            'fiat_payment_method' => 'required_if:payment_method,fiat,in:fpx,card',
            'quantity' => 'required|integer|min:1'
        ]);

        // check offer is still valid by checking available_at and available_until
        $offer = MerchantOffer::where('id', request()->offer_id)
            ->published()
            ->with('merchant', 'merchant.user', 'store', 'claims')
            ->where('available_at', '<=', now())
            ->where('available_until', '>=', now())
            ->where('quantity', '>=', $request->quantity)
            ->first();

        if (!$offer) {
            return response()->json([
                'message' => 'Offer is no longer valid'
            ], 422);
        }

        // calculate net amount
        // ensure user have enough point balance
        $user = request()->user();

        // try {
            if ($request->payment_method == 'points')  {

                $net_amount = $offer->unit_price * $request->quantity;

                // check if enough points
                if ($user->point_balance < $net_amount) {
                    return response()->json([
                        'message' => 'Insufficient Point Balance'
                    ], 422);
                }

                // direct claim
                $offer->claims()->attach($user->id, [
                    // order no is CLAIM(YMd)
                    'order_no' => 'CLAIM-'. date('Ymd') .strtolower(Str::random(3)),
                    'user_id' => $user->id,
                    'quantity' => $request->quantity,
                    'unit_price' => $offer->unit_price,
                    'total' => $offer->unit_price * $request->quantity,
                    'discount' => 0,
                    'tax' => 0,
                    'net_amount' => $net_amount,
                    'status' => MerchantOffer::CLAIM_SUCCESS // status set as 1 as right now the offer should be ready to claim.
                ]); 

                // debit from point ledger
                $this->pointService->debit($offer, $user, $net_amount, 'Claim Offer');

                // reduce quantity
                $offer->quantity = $offer->quantity - $request->quantity;
                $offer->save();

                // fire event
                event(new MerchantOfferClaimed($offer, $user));
            } else if($request->payment_method == 'fiat') {
                $net_amount = (($offer->discounted_fiat_price) ?? $offer->fiat_price)  * $request->quantity;

                // create payment transaction first, not yet claim
                $transaction = $this->transactionService->create(
                    $offer,
                    $net_amount,
                    config('app.default_payment_gateway'),
                    $request->fiat_payment_method
                );

                // if gateway is mpay call mpay service generate Hash for frontend form
                if ($transaction->gateway == 'mpay') {
                    $mpayService = new \App\Services\Mpay(
                        config('services.mpay.mid'),
                        config('services.mpay.hash_key')
                    );

                    // generates required form post fields data for frontend(app) usages
                    $mpayData = $mpayService->createTransaction(
                        $transaction->transaction_no,
                        $net_amount,
                        $transaction->transaction_no,
                        url('/payment/return'),
                        $user->full_phone_no ?? null,
                        $user->email ?? null
                    );
                    
                    // claim but with status await payment
                    $offer->claims()->attach($user->id, [
                        // order no is CLAIM(YMd)
                        'order_no' => 'CLAIM-'. date('Ymd') .strtolower(Str::random(3)),
                        'user_id' => $user->id,
                        'quantity' => $request->quantity,
                        'unit_price' => $offer->unit_price,
                        'total' => $offer->unit_price * $request->quantity,
                        'discount' => 0,
                        'tax' => 0,
                        'net_amount' => $net_amount,
                        'status' => MerchantOffer::CLAIM_AWAIT_PAYMENT // await payment claims
                    ]); 

                    // reduce quantity first if failed in PaymentController will release the quantity if failed
                    $offer->quantity = $offer->quantity - $request->quantity;
                    $offer->save();

                    // Claim is not successful yet, return mpay data for app to redirect (post)
                    return response()->json([
                        'message' => 'Redirect to Gateway',
                        'gateway_data' => $mpayData
                    ], 200);
                }
            }
        // } catch (\Exception $e) {
        //     Log::error($e->getMessage(), [
        //         'offer_id' => $offer->id,
        //         'user_id' => $user->id,
        //         'quantity' => $request->quantity,
        //         'unit_price' => $offer->unit_price,
        //         'total' => $offer->unit_price * $request->quantity,
        //     ]);

        //     return response()->json([
        //         'message' => 'Failed to claim'
        //     ], 422);
        // }

        // refresh offer with latest data
        $offer->refresh();
        return response()->json([
            'message' => 'Claimed successfully',
            'offer' => new MerchantOfferResource($offer)
        ], 200);
    }

    public function getInstoreClaimLink(Request $request)
    {
        $this->validate($request, [
            'offer_id' => 'required|exists:merchant_offers,id'
        ]);
    }

    /**
     * Get My Bookmarked Merchant Offers
     *
     * @param Request $request
     * @return \Illuminate\Http\Response
     *
     * @group Merchant
     * @subgroup Merchant Offers
     * @bodyParam filter string Column to Filter. Example: Filterable columns are:  id, name, description, available_at, available_until, sku
     * @bodyParam filter_value string Value to Filter. Example: Filterable values are: 1, 2, 3, 4, 5, 6, 7, 8, 9, 10
     * @bodyParam sort string Column to Sort. Example: Sortable columns are: id, title, type, slug, status, published_at, created_at, updated_at
     * @bodyParam order string Direction to Sort. Example: Sortable directions are: asc, desc
     * @bodyParam limit integer Per Page Limit Override. Example: 10
     * @bodyParam offset integer Offset Override. Example: 0
     *
     * @response scenario=success {
     *  "data": [],
     *  "links": {},
     *  "meta": {
     *     "current_page": 1,
     *   }
     * }
     *
     */
    public function getMyBookmarkedMerchantOffers(Request $request)
    {
        $query = MerchantOffer::whereHas('interactions', function ($query) {
            $query->where('user_id', auth()->user()->id)
                ->where('type', Interaction::TYPE_BOOKMARK);
        })->published();
        
        $this->buildQuery($query, $request);

        $data = $query->with('user', 'interactions', 'media', 'categories')
            ->paginate(config('app.paginate_per_page'));

        return MerchantOfferResource::collection($data);
    }
}
