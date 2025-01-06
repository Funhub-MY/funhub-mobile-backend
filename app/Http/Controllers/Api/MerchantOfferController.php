<?php

namespace App\Http\Controllers\Api;

use App\Events\MerchantOfferClaimed;
use App\Events\PurchasedMerchantOffer;
use App\Http\Controllers\Controller;
use App\Http\Resources\MerchantOfferClaimResource;
use App\Http\Resources\MerchantOfferResource;
use App\Models\OfferLimitWhitelist;
use App\Http\Resources\PublicMerchantOfferResource;
use App\Models\Interaction;
use App\Models\Merchant;
use App\Models\MerchantOffer;
use App\Models\MerchantOfferClaim;
use App\Models\MerchantOfferVoucher;
use App\Models\ShareableLink;
use App\Models\Transaction;
use App\Models\UserCard;
use App\Notifications\OfferClaimed;
use App\Notifications\OfferRedeemed;
use App\Notifications\PurchasedOfferNotification;
use App\Notifications\VoucherRedeemedNotification;
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
     * @bodyParam merchant_offer_ids array optional Merchant Offer Ids to Filter. Example [1,2,3]
     * @bodyParam city string optional Filter by City. Example: Subang Jaya
     * @bodyParam state string optional Filter by State. Example: Selangor
     * @bodyParam lat float optional Filter by Lat of User (must provide lng). Example: 3.123456
     * @bodyParam lng float optional Filter by Lng of User (must provide lat). Example: 101.123456
     * @bodyParam radius integer optional Filter by Radius (in meters) if provided lat, lng. Example: 10000
     * @bodyParam location_id integer optional Filter by Location Id. Example: 1
     * @bodyParam available_only boolean optional Filter by Available Only. Example: true
     * @bodyParam coming_soon_only boolean optional Filter by Coming Soon Only. Example: true
     * @bodyParam except_expired boolean optional Get all coming soon or available only but hide expired offers. Example: true
     * @bodyParam flash_only boolean optional Filter by Flash Deals Only. Example: true
     * @bodyParam merchant_id integer optional Filter by Merchant ID. Example: 1
     * @bodyParam store_id integer optional Filter by Store ID. Example: 1
     * @bodyParam hide_purchased boolean optional Hide Purchased Offers for current logged in user. Example: true
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
            // ->available()
            ->with([
                'user',
                'user.merchant',
                'user.merchant.media',
                'claims',
                'categories',
                'stores',
                'stores.location',
                'stores.storeRatings',
                'claims',
                'location',
                'location.ratings',
                'media',
                'interactions',
                'views',
                'likes' => function ($query) {
                    $query->where('user_id', auth()->user()->id);
                },
                'interactions' => function ($query) {
                    $query->where('user_id', auth()->user()->id);
                },
            ])
            ->withCount([
                'unclaimedVouchers',
            ]);

        // category_ids filter
        if ($request->has('category_ids')) {
            // explode categories ids
            $category_ids = explode(',', $request->category_ids);
            if (count($category_ids) > 0) {
                $query->whereHas('allOfferCategories', function ($q) use ($category_ids) {
                    $q->whereIn('merchant_offer_categories.id', $category_ids);
                });
            }
        }

        if ($request->has('merchant_offer_ids')) {
            $query->whereIn('id', explode(',', $request->merchant_offer_ids));
        }

        if ($request->has('available_only')) {
            $query->available();
        }

        if ($request->has('coming_soon_only')) {
            $query->where('available_at', '>', now());
        }

        if ($request->has('except_expired')) {
            $query->where('available_until', '>', now());
        }

        if ($request->has('flash_only') && $request->flash_only == 1) {
            $query->flash();
        }

        if ($request->has('flash_only') && $request->flash_only == 0) {
            $query->where('flash_deal', false);
        }

        // get articles by city
        if ($request->has('city')) {
            $query->whereHas('location', function ($query) use ($request) {
                $query->where('city', 'like', '%' . $request->city . '%');
            });
        }

        // get articles by state
        if ($request->has('state')) {
            $query->whereHas('location', function ($query) use ($request) {
                $query->whereHas('state', function($q) use ($request) {
                    $q->where('name', 'like', '%' . $request->state . '%');
                });
            });
        }

        if ($request->has('merchant_id')) {
            $query->whereHas('merchant', function ($query) use ($request) {
                $query->where('id', $request->merchant_id);
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
            // where two condition merchant offer locaiton tagged same location id or merchant offer's stores tagged same location id
            $query->where(function ($subQuery) {
                $subQuery->whereHas('location', function ($query) {
                    $query->where('locations.id', request()->location_id);
                })->orWhereHas('stores', function ($query) {
                    $query->whereHas('location', function ($query) {
                        $query->where('locations.id', request()->location_id);
                    });
                });
            });
        }

        if ($request->has('store_id')) {
            // explode store_id if has comma
            $storeIds = explode(',', $request->store_id);
            $query->whereHas('stores', function ($query) use ($storeIds) {
                $query->whereIn('stores.id', $storeIds);
            });
        }

        if ($request->has('hide_purchased')) {
            $query->whereDoesntHave('claims', function ($query) {
                $query->where('user_id', auth()->user()->id)
                    ->where('status', MerchantOfferClaim::CLAIM_SUCCESS);
            });
        }

        // order by available_at from past first to future
        $query->orderBy('available_at', 'asc');

        $this->buildQuery($query, $request);

        $data = $query->paginate(config('app.paginate_per_page'));

        $userPurchasedBeforeFromMerchantIds = $this->getUserPurchasedBeforeFromMerchantIds($request->user());
        // map userPurchasedBeforeFromMerchantIds to MerchantOfferResource
        $data->map(function ($item, $key) use ($userPurchasedBeforeFromMerchantIds) {
            if (in_array($item->user->id, $userPurchasedBeforeFromMerchantIds)) {
                $item->user_purchased_before_from_merchant = true;
            } else {
                $item->user_purchased_before_from_merchant = false;
            }
            return $item;
        });

        $data = $this->getPointDiscount($data);

        return MerchantOfferResource::collection($data);
    }

    /**
     * Get Point Discount for Offers
     *
     * @param EloquentCollection $offersCollection
     * @return EloquentCollection
     */
    protected function getPointDiscount($offersCollection)
    {
        $user = auth()->user();
        $latestBalancePointsOfUser = cache()->remember('latestBalancePointsOfUser_' . $user->id, 5, function () use ($user) {
            return $this->pointService->getBalanceOfUser($user);
        });

        $offersCollection->each(function ($offer) use ($latestBalancePointsOfUser) {
            $price = $offer->fiat_price;
            $priceValueOfPoints = config('app.funbox_ringgit_value');

            $maxPointsForPrice = floor($price / $priceValueOfPoints);
            $offer->available_points_to_discount = min($latestBalancePointsOfUser, $maxPointsForPrice);

            $discountAmount = $offer->available_points_to_discount * $priceValueOfPoints;
            $offer->price_after_discount_with_points = max(0, $price - $discountAmount);
        });

        return $offersCollection;
    }

    /**
     * Get User Purchased Before From Merchant Ids
     *
     * @param $user
     * @return array
     */
    protected function getUserPurchasedBeforeFromMerchantIds($user)
    {
        $userPurchasedBeforeFromMerchantIds = [];
        if ($user && config('app.same_merchant_spend_limit')) {
            // Check if the user belongs to the limit whitelist first if whitelisted, they can repeatedly buy any merchant offers
            $isWhitelisted = OfferLimitWhitelist::where('user_id', $user->id)->exists();

            if (!$isWhitelisted) {
                $merchantIds = MerchantOfferClaim::where('user_id', $user->id)
                    ->where('status', MerchantOfferClaim::CLAIM_SUCCESS)
                    ->where('created_at', '>=', now()->subDays(config('app.same_merchant_spend_limit_days')))
                    ->pluck('merchant_offer_id')
                    ->toArray();

                $userPurchasedBeforeFromMerchantIds = MerchantOffer::whereIn('id', $merchantIds)
                    ->pluck('user_id')
                    ->unique()
                    ->toArray();

                Log::info('User purchased before from merchant ids', [
                    'user_id' => $user->id,
                    'user_purchased_before_from_merchant_ids' => $userPurchasedBeforeFromMerchantIds,
                ]);
            } else {
                Log::info('User in whitelist', [
                    'user_id' => $user->id,
                ]);
            }
        }

        return $userPurchasedBeforeFromMerchantIds;
    }


    /**
     * Get My Merchant Offers (Logged in User)
     *
     * @return \Illuminate\Http\JsonResponse
     *
     * @group Merchant
     * @subgroup Merchant Offers
     * @urlParam is_redeemed number optional Filter by Redeemed. Example: 0/1
     * @urlParam is_expired number optional Filter by Expired. Example: 0/1
     * @urlParam claim_id integer optional Filter by Claim ID. Example: 1
     * @response scenario=success {
     * "data": []
     * }
     */
    public function getMyMerchantOffers(Request $request)
    {
        // get merchant offers claimed by user
        $query = MerchantOfferClaim::where('user_id', auth()->user()->id)
            ->where('status', MerchantOfferClaim::CLAIM_SUCCESS);

        if ($request->has('claim_id')) {
            $query->where('id', $request->claim_id);
        }

        if ($request->has('is_redeemed')) {
            if ($request->get('is_redeemed') == 1) { // true
                $query->whereHas('redeem');
            } else if ($request->get('is_redeemed') == 0) { // false
                $query->whereDoesntHave('redeem');
            }
        }

        if ($request->has('is_expired')) {
            if ($request->get('is_expired') == 1) { // true
                $query->where(function ($query) {
                    $query->whereHas('merchantOffer', function ($query) {
                        $query->whereRaw('DATE_ADD(DATE(merchant_offer_user.created_at), INTERVAL merchant_offers.expiry_days + 1 DAY) < CURDATE()');
                    });
                });
            } else if ($request->get('is_expired') == 0) { // false
                $query->where(function ($query) {
                    $query->whereHas('merchantOffer', function ($query) {
                        $query->whereRaw('DATE_ADD(DATE(merchant_offer_user.created_at), INTERVAL merchant_offers.expiry_days + 1 DAY) >= CURDATE()');
                    });
                });
            }
        }

        $claims = $query->with('merchantOffer', 'redeem', 'voucher', 'merchantOffer.user', 'merchantOffer.user.merchant', 'merchantOffer.stores', 'merchantOffer.stores.location', 'merchantOffer.stores.storeRatings', 'merchantOffer.categories')
            ->paginate(config('app.paginate_per_page'));

        return MerchantOfferClaimResource::collection($claims);
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
        // $offer = MerchantOffer::where('id', $id)->first();

        // $offer->load([
        //     'user',
        //     'user.merchant',
        //     'user.merchant.media',
        //     'claims',
        //     'categories',
        //     'stores',
        //     'stores.location',
        //     'stores.storeRatings',
        //     'claims',
        //     'location',
        //     'location.ratings',
        //     'media',
        //     'interactions',
        //     'views',
        //     'likes' => function ($query) {
        //         $query->where('user_id', auth()->user()->id);
        //     },
        //     'interactions' => function ($query) {
        //         $query->where('user_id', auth()->user()->id);
        //     },
        // ])
        // ->loadCount([
        //     'unclaimedVouchers',
        // ]);

        $offer = MerchantOffer::query()
            ->where('id', $id)
            // ->available()
            ->with([
                'user',
                'user.merchant',
                'user.merchant.media',
                'claims',
                'categories',
                'stores',
                'stores.location',
                'stores.storeRatings',
                'claims',
                'location',
                'location.ratings',
                'media',
                'interactions',
                'views',
                'likes' => function ($query) {
                    $query->where('user_id', auth()->user()->id);
                },
                'interactions' => function ($query) {
                    $query->where('user_id', auth()->user()->id);
                },
            ])
            ->withCount([
                'unclaimedVouchers',
            ])->first();


        // ensure customer should not see offer from same user within time span of config('app.same_merchant_spend_limit_days') if they have purchased
        // eg. customer buy from Merchant A offer A today, they should not see Merchant A offer A for next 30 days
        $userPurchasedBeforeFromMerchantIds = $this->getUserPurchasedBeforeFromMerchantIds(auth()->user());

        // override $offer->user_purchased_before_from_merchant with the value
        $offer->user_purchased_before_from_merchant = in_array($offer->user->id, $userPurchasedBeforeFromMerchantIds) ? true : false;

        $offer = $this->getPointDiscount(collect([$offer]))->first();
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
     * @bodyParam card_id integer required_if:fiat_payment_method,card Card ID. Example: 1
     * @bodyParam wallet_type string optional Wallet Type. Example: TNG/FPX-CIMB
     * @bodyParam channel string optional Channel. Example: app/funhub_web
     * @bodyParam email string optional Email for web channel to fire purchased email after successful purchase. Example: john@email.com
     * @bodyParam use_point_discount boolean optional Use Point(Funbox) Discount. Example: 1
     * @bodyParam points_to_use integer optional Points(Funbox) to Use. Example: 2
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
            'card_id' => 'exists:user_cards,id',
            'quantity' => 'required|integer|min:1',
            'use_point_discount' => 'nullable|boolean',
            'points_to_use' => 'nullable|required_if:use_point_discount,true|integer|exists:point_ledgers,id',
			'referral_code' => 'nullable|string',
        ]);

        // check offer is still valid by checking available_at and available_until, available quantity check is at next statement
        $offer = MerchantOffer::where('id', request()->offer_id)
            ->published()
            ->with('user', 'user.merchant', 'store', 'claims')
            ->where('available_at', '<=', now())
            ->where('available_until', '>=', now())
            ->first();

        if (!$offer) {
            return response()->json([
                'message' => __('messages.error.merchant_offer_controller.Offer_is_no_longer_valid')
            ], 422);
        }

        // check available quantity of vouchers, even though locked vouchers will be counted as it will be releaed when failed payment/15min voucher release lock
        if ($offer->unclaimedVouchers()->count() < $request->quantity) {
            return response()->json([
                'message' => __('messages.error.merchant_offer_controller.Offer_is_sold_out'),
                'quantity' => $request->quantity,
                'available_quantity' => $offer->unclaimedVouchers()->count(),
                'user_id' => auth()->user()->id,
                'offer_id' => $offer->id,
            ], 422);
        }

        $user = request()->user();
        $claim = null;
        $redemption_start_date = null;
        $redemption_end_date = null;
        if ($request->payment_method == 'points') {
            // ------------------------------------ POINTS CHECKOUT ------------------------------------

            $net_amount = $offer->unit_price * $request->quantity;
            $voucher = $offer->unclaimedVouchers()->orderBy('id', 'asc')->first();

            // check if enough points
            $userPointBalance = $this->pointService->getBalanceOfUser($user);
            if ($userPointBalance < $net_amount) {
                return response()->json([
                    'message' => __('messages.error.merchant_offer_controller.Insufficient_Point_Balance')
                ], 422);
            }

            // direct claim
            $orderNo = 'C' . date('Ymd') .strtoupper(Str::random(5));

            $offer->claims()->attach($user->id, [
                // order no is CLAIM(YMd)
                'order_no' => $orderNo,
                'user_id' => $user->id,
                'quantity' => $request->quantity,
                'unit_price' => $offer->unit_price,
                'total' => $offer->unit_price * $request->quantity,
                'purchase_method' => 'points',
                'discount' => 0,
                'tax' => 0,
                'net_amount' => $net_amount,
                'voucher_id' => $voucher->id,
                'status' => MerchantOffer::CLAIM_SUCCESS // status set as 1 as right now the offer should be ready to claim.
            ]);
            // update voucher owned_by_id to this user
            $voucher->update(['owned_by_id' => $user->id]);

            // debit from point ledger
            $this->pointService->debit($offer, $user, $net_amount, 'Claim Offer');

            // reduce quantity
            $offer->quantity = $offer->quantity - $request->quantity;
            $offer->save();

            // fire event
            event(new MerchantOfferClaimed($offer, $user));

            event(new PurchasedMerchantOffer($user, $offer, 'points'));

            $claim = MerchantOfferClaim::where('order_no', $orderNo)->first();
			$redemption_start_date = $claim->created_at;
			$redemption_end_date = $claim->created_at->addDays($offer->expiry_days)->endOfDay();

			$encrypted_data = $this->processEncrypt([
				'offer_id' => $offer->id,
				'claim_id' => $claim->id,
				'phone_no' => $user->phone_no
			]);
			$merchantOfferCover = $offer->getFirstMediaUrl(MerchantOffer::MEDIA_COLLECTION_NAME);

			if ($user->email) {
				try {
					$user->notify(new PurchasedOfferNotification(
						$claim->order_no,
						$claim->updated_at,
						$offer->name,
						$request->quantity, $net_amount,
						'points',
						$claim->created_at->format('Y-m-d'),
						$claim->created_at->format('H:i:s'),
						$redemption_start_date ? $redemption_start_date->format('j/n/Y') : null,
						$redemption_end_date ? $redemption_end_date->format('j/n/Y') : null,
						$encrypted_data,
						$claim->merchantOffer->user->merchant->brand_name,
						$claim->merchantOffer->user->name,
						$merchantOfferCover
					));
				} catch (\Exception $e) {
					Log::error('Error sending PurchasedOfferNotification: ' . $e->getMessage());
				}

            }

            try {
                // notify user offer claimed
                $user->notify(new OfferClaimed($offer, $user, 'points', $net_amount));
            } catch (\Exception $e) {
                Log::error($e->getMessage(), [
                    'user_id' => $user->id,
                    'offer_id' => $offer->id,
                ]);
            }

        } else if($request->payment_method == 'fiat') {
			$channel = $request->has('channel') ? $request->channel : 'app';
			// ------------------------------------ CASH (FPX/CARD/WALET) CHECKOUT ------------------------------------
            // check if user has verified email address
            if ($channel === 'app' && !$user->hasVerifiedEmail()) {
                return response()->json([
                    'message' => __('messages.error.merchant_offer_controller.Please_verify_your_email_address_first')
                ], 422);
            }
            $amount = (($offer->discounted_fiat_price) ?? $offer->fiat_price)  * $request->quantity;
            $net_amount = (($offer->discounted_fiat_price) ?? $offer->fiat_price)  * $request->quantity;
            $walletType = null;

            // if request has payment type, then use it
            if ($request->has('wallet_type')) {
                $walletType = $request->wallet_type;
            }

            // get card if payment mode via card
            $selectedCard = null;
            if ($request->fiat_payment_method == 'card' && $request->has('card_id')) {
                // check user has a saved card
                $selectedCard = UserCard::where('id', $request->card_id)
                    ->where('user_id', $user->id)
                    // ->where('is_default', true)
                    // ->notExpired()
                    ->first();
            }

            // discount using funbox logic (Point)
            // DEPRECATED
            $discount_amount = 0;
            if ($request->has('use_point_discount')
                && $request->use_point_discount == true
                && $request->has('points_to_use')) {
                // make sure net amount is wlays using not discounted fiat_price
                $net_amount = $offer->fiat_price &  $request->quantity;

                $pointService = new PointService();
                $latestBalancePointsOfUser = $pointService->getBalanceOfUser($user);

                // check if user has enough points to use
                if ($latestBalancePointsOfUser < $request->points_to_use) {
                    return response()->json([
                        'message' => __('messages.error.merchant_offer_controller.Insufficient_Point_Balance')
                    ], 422);
                }

                $discount_amount = $request->points_to_use * config('app.funbox_ringgit_value');
                $net_amount = $net_amount - $discount_amount;
            }

            // create payment transaction first, not yet claim
            $transaction = $this->transactionService->create(
                $offer,
                ($discount_amount > 0) ? $amount : $net_amount, // if has discount, log amount is raw amount, if not just use net amount
                config('app.default_payment_gateway'),
                $user->id,
                ($walletType) ? $walletType : $request->fiat_payment_method,
                $request->get('channel', 'app'),
                $request->get('email', null),
                $request->get('channel') === 'funhub_web' ? $request->get('name') : $user->name,
				$request->get('referral_code'),
			);

            // if gateway is mpay call mpay service generate Hash for frontend form
            if ($transaction->gateway == 'mpay') {
                $voucher = $offer->unclaimedVouchers()->orderBy('id', 'asc')->first();

                $mpayService = new \App\Services\Mpay(
                    config('services.mpay.mid'),
                    config('services.mpay.hash_key'),
                    ($request->fiat_payment_method) ? $request->fiat_payment_method : false,
                );

                // generates required form post fields data for frontend(app) usages
                $mpayData = $mpayService->createTransaction(
                    $transaction->transaction_no,
                    $net_amount, // always use net amount for gateway checkout
                    $transaction->transaction_no,
                    secure_url('/payment/return'),
                    $user->full_phone_no ?? null,
                    $user->email ?? null,
                    $walletType, // FPX-CIMB,GRAB,TNG
                    $selectedCard ? $selectedCard->card_token : null,
                    $user->id
                );

                $orderNo = 'CLAIM-'. date('Ymd') .strtoupper(Str::random(3));
                $offer->claims()->attach($user->id, [
                    // order no is CLAIM(YMd)
                    'order_no' => $orderNo,
                    'user_id' => $user->id,
                    'quantity' => $request->quantity,
                    'unit_price' => $offer->unit_price,
                    'total' => ($discount_amount > 0) ? $amount : $net_amount,
                    'purchase_method' => 'fiat',
                    'discount' => 0,
                    'tax' => 0,
                    'net_amount' => $net_amount,
                    'voucher_id' => $voucher->id,
                    'transaction_no' => $transaction->transaction_no, // store transaction no for later use
                    'status' => MerchantOffer::CLAIM_AWAIT_PAYMENT // await payment claims
                ]);

                // update voucher owned_by_id to this user
                $voucher->update(['owned_by_id' => $user->id]); // lock down first

                // reduce quantity first if failed in PaymentController will release the quantity if failed
                $offer->quantity = $offer->quantity - $request->quantity;
                $offer->save();

                // fire event
                event(new PurchasedMerchantOffer($user, $offer, 'fiat'));

                // Claim is not successful yet, return mpay data for app to redirect (post)
                return response()->json([
                    'message' => __('messages.success.merchant_offer_controller.Redirect_to_Gateway'),
                    'gateway_data' => $mpayData
                ], 200);
            }
        }

        // refresh offer with latest data
        $offer->refresh();
        return response()->json([
            'message' => __('messages.success.merchant_offer_controller.Claimed_successfully'),
            'offer_claim_id' => $claim->id,
            'server_time' => Carbon::now(),
            'redemption_start_date' => $redemption_start_date,
            'redemption_end_date' => $redemption_end_date,
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

        $data = $query->with('user', 'interactions', 'stores', 'stores.location', 'stores.storeRatings', 'media', 'categories')
            ->paginate(config('app.paginate_per_page'));

        return MerchantOfferResource::collection($data);
    }

    /**
     * Cancel a Merchant Offer Transaction
     *
     * @param Request $request
     * @return void
     *
     * @group Merchant
     * @subgroup Merchant Offers
     * @bodyParam merchant_offer_id integer required Merchant Offer ID. Example: 1
     * @response scenario=success {
     * "message": "Transaction cancelled"
     * }
     * @response scenario=offer_pending_payment_not_found {
     * "message": "You have not claimed this offer"
     * }
     */
    public function postCancelTransaction(Request $request)
    {
        $this->validate($request, [
            'merchant_offer_id' => 'required|exists:merchant_offers,id',
        ]);

        // check if user has claimed this merchant offer or not where status is await payment
        $offer = MerchantOffer::where('id', $request->merchant_offer_id)
            ->whereHas('claims', function ($query) {
                $query->where('user_id', auth()->user()->id)
                    ->where('merchant_offer_user.status', MerchantOffer::CLAIM_AWAIT_PAYMENT);
            })->first();

        Log::info('[ProductController] User cancelled transaction, but status maintain PENDING, offer quantity still locked', ['offer' => $offer, 'user_id' => auth()->user()->id]);

        return response()->json([
            'message' => __('messages.success.merchant_offer_controller.Transaction_cancelled')
        ], 200);
        // if ($offer) {
            // // release quantity back to MerchantOffer
            // $offer->quantity = $offer->quantity + $offer->claims()->where('user_id', auth()->user()->id)
            //     ->wherePivot('status', MerchantOffer::CLAIM_AWAIT_PAYMENT)
            //     ->first()->pivot->quantity;
            // $offer->save();

            // $claim = MerchantOfferClaim::where('merchant_offer_id', $offer->id)
            //     ->where('user_id', auth()->user()->id)
            //     ->where('status', MerchantOffer::CLAIM_AWAIT_PAYMENT)
            //     ->latest()
            //     ->first();

            // // release voucher back to MerchantOfferVoucher
            // // get voucher_id from claims
            // $voucher_id = $claim->voucher_id;

            // if ($voucher_id) {
            //     $voucher = MerchantOfferVoucher::where('id', $voucher_id)->first();
            //     if ($voucher) {
            //         $voucher->owned_by_id = null;
            //         $voucher->save();
            //         Log::info('[MerchantOfferController] Voucher released', [$voucher->toArray()]);
            //     }
            // } else {
            //     Log::info('[MerchantOfferController] Voucher not found not able to release', [
            //         'offer_id' => $offer->id,
            //         'offer_claims_data' => $claim->toArray(),
            //         'user_id' => auth()->user()->id,
            //     ]);
            // }

            // // change status to failed
            // $claim->update([
            //     'status' => MerchantOffer::CLAIM_FAILED,
            //     'voucher_id' => null
            // ]);

            // Log::info('[MerchantOfferController] Offer quantity released and voucher released', [
            //     'offer_id' => $offer->id,
            //     'user_id' => auth()->user()->id,
            // ]);

            // // change associated transaction status to failed
            // $transactionRecord = $offer->transactions()->where('user_id', auth()->user()->id)
            //     ->where('status', Transaction::STATUS_PENDING)
            //     ->first();
            // if ($transactionRecord) {
            //     $transaction = $this->transactionService->updateTransactionStatus($transactionRecord->id, Transaction::STATUS_FAILED);
            //     Log::info('[MerchantOfferController] Transaction status updated', [
            //         'transaction_id' => $transaction->toArray(),
            //         'status' => Transaction::STATUS_FAILED,
            //         'user_id' => auth()->user()->id,
            //     ]);
            // }

            // return response()->json([
            //     'message' => 'Transaction cancelled'
            // ], 200);
        // } else {
        //     Log::info('[MerchantOfferController] Offer pending payment not found', [
        //         'offer_id' => $request->merchant_offer_id,
        //         'user_id' => auth()->user()->id,
        //     ]);
        //     return response()->json([
        //         'message' => 'You have not claimed this offer'
        //     ], 422);
        // }
    }

    /**
     * Redeem a Merchant Offer (In-Store)
     * This is when customer with claimed merchant offer wishes to redeem in store
     *
     * @param Request $request
     * @return \Illuminate\Http\Response
     *
     * @group Merchant
     * @subgroup Merchant Offers
     * @bodyParam claim_id integer required Claim ID. Example: 1
     * @bodyParam offer_id integer required Merchant Offer ID. Example: 1
     * @bodyParam quantity integer required Quantity to Redeem. Example: 1
     * @bodyParam redeem_code string required Redemption Code Provided by Merchant. Example: 123456
     *
     * @response scenario=success {
     * "message": "Redeemed successfully",
     * "offer": {
     * }
     * }
     */
    public function postRedeemOffer(Request $request)
    {
        $this->validate($request, [
            'claim_id' => 'required',
            'offer_id' => 'required|exists:merchant_offers,id',
            'quantity' => 'required|integer|min:1',
            'redeem_code' => 'required'
        ]);

        // check if user has claimed this merchant offer or not
        $offer = MerchantOffer::where('id', $request->offer_id)
            ->whereHas('claims', function ($query) {
                $query->where('user_id', auth()->user()->id);
            })->first();

        if (!$offer) {
            return response()->json([
                'message' => __('messages.error.merchant_offer_controller.You_have_not_claimed_this_offer')
            ], 422);
        }

        // if theres expiry for after purchase
        if ($offer && $offer->expiry_days > 0) {
            // check offer expiry_days with claim created_at date days diff with now to see if expired
            $userClaim = $offer->claims()->where('user_id', auth()->user()->id)
                ->wherePivot('status', MerchantOffer::CLAIM_SUCCESS)
                ->wherePivot('id', '=', $request->claim_id)
                ->first();

            if (!$userClaim) {
                return response()->json([
                    'message' => __('messages.error.merchant_offer_controller.You_have_not_claimed_this_offer')
                ], 422);
            }
            Log::info('user claim', [
                $userClaim->toArray(), Carbon::parse($userClaim->pivot->created_at),
                Carbon::parse($userClaim->pivot->created_at)->endOfDay()->addDays($offer->expiry_days),
                Carbon::parse($userClaim->pivot->created_at)->endOfDay()->addDays($offer->expiry_days)->isPast()
            ]);

            if (Carbon::parse($userClaim->pivot->created_at)->endOfDay()->addDays($offer->expiry_days)->isPast()) {
                return response()->json([
                    'message' => __('messages.error.merchant_offer_controller.This_offer_has_expired')
                ], 422);
            }
        }

        // get claim without redeemed
        $claim = MerchantOfferClaim::where('id', $request->claim_id)
            ->where('user_id', auth()->user()->id)
            ->whereDoesntHave('redeem')
            ->first();

        // already redeemed fully
        if (!$claim || $claim->quantity < $request->quantity) {
            return response()->json([
                'message' => __('messages.error.merchant_offer_controller.You_do_not_have_enough_to_redeem')
            ], 422);
        }

        // check if merchant code is valid
        // note merchant is hasOneThrough user as we only attach merhcnat offer direct to user
        $merchant = $offer->whereHas('user.merchant', function ($query) use ($request) {
            $query->where('redeem_code', $request->redeem_code);
        })->exists();

        if (!$merchant) {
            return response()->json([
                'message' => __('messages.error.merchant_offer_controller.Invalid_merchant_redeem_code')
            ], 422);
        }

        // merchant code validated proceed create redeems
        $redeem = $offer->redeems()->attach(auth()->user()->id, [
            'claim_id' => $request->claim_id,
            'quantity' => $request->quantity,
        ]);

        // reload offer
        $offer->refresh();

        // notify
        try {
            $locale = auth()->user()->last_lang ?? config('app.locale');
            auth()->user()->notify((new OfferRedeemed($offer, auth()->user()))->locale($locale));
        } catch (\Exception $e) {
            Log::error('Error sending offer redeemed notification', [$e->getMessage()]);
        }

        // Send notification to merchant user email
        try {
            $user = auth()->user();
            $username = $user->username;
            $userEmail = null;

            if ($user->email) {
                $userEmail = $user->email;
            }

            if ($offer->user->email) {
                $offer->user->notify(new VoucherRedeemedNotification($username, $userEmail, $offer->user->name, $offer));
            }
        } catch (\Exception $e) {
            Log::error('Error sending offer redeemed notification to merchant', [$e->getMessage()]);
        }

        return response()->json([
            'message' => __('messages.success.merchant_offer_controller.Redeemed_Successfully'),
            'offer' => new MerchantOfferResource($offer)
        ], 200);
    }

    /**
     * Web - Get Public Offers
     *
     * @param Request $request
     * @return JsonResponse
     *
     * @group Merchant
     * @urlParam category_ids array optional Category Ids to Filter. Example: 1,2,3
     * @urlParam merchant_offer_ids array optional Merchant Offer Ids to Filter. Example: 1,2,3
     * @urlParam city string optional Filter by City. Example: Subang Jaya
     * @urlParam state string optional Filter by State. Example: Selangor
     * @urlParam lat float optional Filter by Lat of User (must provide lng). Example: 3.123456
     * @urlParam lng float optional Filter by Lng of User (must provide lat). Example: 101.123456
     * @urlParam radius integer optional Filter by Radius (in meters) if provided lat, lng. Example: 10000
     * @urlParam available_only boolean optional Filter by Available Only. Example: true or 0
     * @urlParam coming_soon_only boolean optional Filter by Coming Soon Only. Example: true or 0
     */
    public function getPublicOffers(Request $request)
    {
        // ensure only published offers
        $query = MerchantOffer::query()
            ->published()
            ->where('available_for_web', true)
            // ->available()
            ->with([
                'user',
                'user.merchant',
                'user.merchant.media',
                'claims',
                'categories',
                'stores',
                'stores.location',
                'stores.storeRatings',
                'claims',
                'location',
                'location.ratings',
                'media',
                'interactions',
                'views',
                // 'likes' => function ($query) {
                //     $query->where('user_id', auth()->user()->id);
                // },
                // 'interactions' => function ($query) {
                //     $query->where('user_id', auth()->user()->id);
                // },
            ])
            ->withCount([
                'unclaimedVouchers',
            ]);

        // category_ids filter
        if ($request->has('category_ids')) {
            // explode categories ids
            $category_ids = explode(',', $request->category_ids);
            if (count($category_ids) > 0) {
                $query->whereHas('allOfferCategories', function ($q) use ($category_ids) {
                    $q->whereIn('merchant_offer_categories.id', $category_ids);
                });
            }
        }

        if ($request->has('merchant_offer_ids')) {
            $query->whereIn('id', explode(',', $request->merchant_offer_ids));
        }

        if ($request->has('available_only')) {
            $query->available();
        }

        if ($request->has('coming_soon_only')) {
            $query->where('available_at', '>', now());
        }

        if ($request->has('except_expired')) {
            $query->where('available_until', '>', now());
        }

        if ($request->has('flash_only') && $request->flash_only == 1) {
            $query->flash();
        }

        if ($request->has('flash_only') && $request->flash_only == 0) {
            $query->where('flash_deal', false);
        }

        // get articles by city
        if ($request->has('city')) {
            $query->whereHas('location', function ($query) use ($request) {
                $query->where('city', 'like', '%' . $request->city . '%');
            });
        }

        // get articles by state
        if ($request->has('state')) {
            $query->whereHas('location', function ($query) use ($request) {
                $query->whereHas('state', function($q) use ($request) {
                    $q->where('name', 'like', '%' . $request->state . '%');
                });
            });
        }

        if ($request->has('merchant_id')) {
            $query->whereHas('merchant', function ($query) use ($request) {
                $query->where('id', $request->merchant_id);
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
            // where two condition merchant offer locaiton tagged same location id or merchant offer's stores tagged same location id
            $query->where(function ($subQuery) {
                $subQuery->whereHas('location', function ($query) {
                    $query->where('locations.id', request()->location_id);
                })->orWhereHas('stores', function ($query) {
                    $query->whereHas('location', function ($query) {
                        $query->where('locations.id', request()->location_id);
                    });
                });
            });
        }

        if ($request->has('store_id')) {
            // explode store_id if has comma
            $storeIds = explode(',', $request->store_id);
            $query->whereHas('stores', function ($query) use ($storeIds) {
                $query->whereIn('stores.id', $storeIds);
            });
        }

        // order by available_at from past first to future
        $query->orderBy('available_at', 'asc');

        $this->buildQuery($query, $request);

        $data = $query->paginate(config('app.paginate_per_page'));

        return PublicMerchantOfferResource::collection($data);
    }

    /**
     * Web - Get Single Public Offer
     *
     * @param Request $request
     * @return JsonResponse
     *
     * @group Merchant
     * @urlParam id integer optional The id of the merchant offer. Example: 1
     * @urlParam sku string optional The id of the merchant offer. Example: ABC-1234
     * @response scenario=success {
     * "offer": {}
     * }
     */
    public function getPublicOferSingle(Request $request)
    {
        $this->validate($request, [
            'id' => 'required_if:sku,null|integer',
            'sku' => 'required_if:id,null',
        ]);

        if ($request->has('id')) {
            $offer = MerchantOffer::where('id', $request->id)
                ->published()
                // ->where('available_for_web', true)
                ->first();
        } else {
            $offer = MerchantOffer::where('sku', $request->sku)
                ->published()
                // ->where('available_for_web', true)
                ->first();
        }

        if (!$offer) {
            return response()->json(['message' => __('messages.error.merchant_offer_controller.Deal_not_found')], 404);
        }

        return response()->json([
            'offer' => new PublicMerchantOfferResource($offer)
        ]);
    }

    /**
     * Get Merchant Offer Public
     *
     * @param Request $request
     * @return void
     */
    public function getPublicOfferPublicView(Request $request)
    {
        $this->validate($request, [
            'share_code' => 'required|string'
        ]);
        // get merchant offer by ShareableLink
        $share = ShareableLink::where('link', $request->share_code)
            ->where('model_type', MerchantOffer::class)
            ->first();

        if (!$share) {
            return abort(404);
        }

        // find offer by model_id
        $offer = MerchantOffer::where('id', $share->model_id)
            ->published()
            ->first();

        if (!$offer) {
            return response()->json(['message' => __('messages.error.merchant_offer_controller.Deal_not_found')], 404);
        }

        // return user profile
        return response()->json([
            'offer' => new PublicMerchantOfferResource($offer)
        ]);
    }

    /**
     * Get Merchant Offers Nearby
     *
     * @param Request $request
     * @return void
     *
     * @group Merchant
     * @subgroup Merchant Offers
     *
     * @queryParam  category_ids array optional Merchant Category Ids to Filter. Example: [1, 2, 3]
     * @queryParam  merchant_offer_ids array optional Merchant Offer Ids to Filter. Example [1,2,3]
     * @queryParam  city string optional Filter by City Name. Example: Subang Jaya
     * @queryParam  state string optional Filter by State. Example: Selangor
     * @queryParam  lat float required Filter by Lat of User (must provide lng). Example: 3.123456
     * @queryParam  lng float required Filter by Lng of User (must provide lat). Example: 101.123456
     * @queryParam  radius integer optional Filter by Radius (in meters) if provided lat, lng. Example: 10000
     * @queryParam  available_only boolean optional Filter by Available Only. Example: true
     * @queryParam  coming_soon_only boolean optional Filter by Coming Soon Only. Example: true
     * @queryParam except_expired boolean optional Get all coming soon or available only but hide expired offers. Example: true
     * @queryParam  flash_only boolean optional Filter by Flash Deals Only. Example: true
     * @queryParam  limit integer optional Per Page Limit Override. Example: 10
     *
     * @response scenario=success {
     * "data": [],
     * }
     */
    public function getMerchantOffersNearby(Request $request)
    {
        $radius = $request->has('radius') ? $request->radius : config('app.location_default_radius');

        if ($request->has('city')) {
            // City-based search remains the same...
            $query = MerchantOffer::query();
            $query->whereHas('location', function ($query) use ($request) {
                $query->where('city', 'like', '%' . $request->city . '%');
            });

            // pass to query builder
            $data = $this->merchantOfferQueryBuilder($query, $request)
                ->paginate($request->has('limit') ? $request->limit : config('app.paginate_per_page'));
        } else {
            // step 1: get nearby offers using Algolia's geosearch
            $searchQuery = MerchantOffer::search('')->with([
                'aroundLatLng' => $request->lat . ',' . $request->lng,
                'aroundRadius' => $radius * 1000,
                'aroundPrecision' => 50,
                'getRankingInfo' => true,
            ]);

            // get the IDs of nearby offers
            $nearbyOfferIds = $searchQuery->get()->pluck('id')->toArray();

            // step 2: apply additional filters 
            $query = MerchantOffer::whereIn('id', $nearbyOfferIds)
                ->orderByRaw("FIELD(id, " . implode(',', $nearbyOfferIds) . ") ASC");
            // pass to query builder
            $data = $this->merchantOfferQueryBuilder($query, $request)
                ->paginate($request->has('limit') ? $request->limit : config('app.paginate_per_page'));
        }
        return MerchantOfferResource::collection($data);
    }

    public function merchantOfferQueryBuilder($query, $request)
    {
        $query->published();

        // category_ids filter
        if ($request->has('category_ids')) {
            // explode categories ids
            $category_ids = is_array($request->category_ids) ? $request->category_ids : explode(',', $request->category_ids);
            if (count($category_ids) > 0) {
                $query->whereHas('allOfferCategories', function ($q) use ($category_ids) {
                    $q->whereIn('merchant_offer_categories.id', $category_ids);
                });
            }
        }

        if ($request->has('merchant_offer_ids')) {
            $query->whereIn('id', explode(',', $request->merchant_offer_ids));
        }

        if ($request->has('available_only')) {
            $query->available();
        }

        if ($request->has('coming_soon_only')) {
            $query->where('available_at', '>', now());
        }

        if ($request->has('except_expired')) {
            $query->where('available_until', '>', now());
        }

        if ($request->has('flash_only') && $request->flash_only == 1) {
            $query->flash();
        }

        if ($request->has('flash_only') && $request->flash_only == 0) {
            $query->where('flash_deal', false);
        }

        // get articles by city
        if ($request->has('city')) {
            $query->whereHas('location', function ($query) use ($request) {
                $query->where('city', 'like', '%' . $request->city . '%');
            });
        }

        // get articles by state
        if ($request->has('state')) {
            $query->whereHas('location', function ($query) use ($request) {
                $query->whereHas('state', function($q) use ($request) {
                    $q->where('name', 'like', '%' . $request->state . '%');
                });
            });
        }

        if ($request->has('merchant_id')) {
            $query->whereHas('merchant', function ($query) use ($request) {
                $query->where('id', $request->merchant_id);
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

        $query ->with([
            'user',
            'user.merchant',
            'user.merchant.media',
            'claims',
            'categories',
            'stores' => function ($query) use ($request) {
                $query->withDistance($request->lat, $request->lng)
                      ->orderBy('distance', 'ASC');
            },
            'stores.location',
            'stores.storeRatings',
            'claims',
            'location',
            'location.ratings',
            'media',
            'interactions',
            'views',
            'likes' => function ($query) {
                $query->where('user_id', auth()->user()->id);
            },
            'interactions' => function ($query) {
                $query->where('user_id', auth()->user()->id);
            },
        ])
        ->withCount([
            'unclaimedVouchers',
        ]);

        return $query;
    }

    /**
     * Get Last Purchase Date of user on a Merchant User
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     *
     * @group Merchant
     * @subgroup Merchant Offers
     * @bodyParam merchant_user_id integer required Merchant User ID(merchant->user->id). Example: 1
     * @response scenario=success {
     * "last_purchase_offer_id": 1,
     * "last_purchase_date": "2023-08-01 12:00:00"
     * }
     */
    public function getLastPurchaseDateFromMerchantUser(Request $request)
    {
        $this->validate($request, [
            'merchant_user_id' => 'required|exists:users,id'
        ]);

        // check any MerchantOfferClaims for this auth user has the merchant user
        $claim = MerchantOfferClaim::where('user_id', auth()->user()->id)
            ->whereHas('merchantOffer', function ($query) use ($request) {
                $query->where('user_id', $request->merchant_user_id);
            })
            ->latest()
            ->where('status', MerchantOffer::CLAIM_SUCCESS)
            ->first();

        if ($claim) {
            return response()->json([
                'last_purchase_offer_id' => $claim->merchant_offer_id,
                'last_purchase_date' => $claim->created_at
            ]);
        } else {
            return response()->json([
                'last_purchase_offer_id' => null,
                'last_purchase_date' => null
            ]);
        }
    }

    public function processEncrypt($data) {
        $checkout_secret = config('app.funhub_checkout_secret');

        try {
            // we use the same key and IV
            $key = hex2bin($checkout_secret);
            $iv =  hex2bin($checkout_secret);

            // we receive the encrypted string from the post
            // finally we trim to get our original string
            $encrypted_data = openssl_encrypt(json_encode($data), 'AES-128-CBC', $key, 0, $iv);

            if ($encrypted_data === false) {
                Log::error('Error encrypting data', [
                    'error' => 'Encryption Failed - '. openssl_error_string(),
                    'data' => json_encode($data),
                ]);
            }

            return $encrypted_data;

        } catch (\Exception $e) {
            Log::error('Error encrypting data', [
                'error' => $e->getMessage(),
                'data' => $data
            ]);

            return '';
        }
    }
    
}
