<?php

namespace App\Http\Controllers\Api;

use App\Events\MerchantOfferClaimed;
use App\Http\Controllers\Controller;
use App\Http\Resources\MerchantOfferResource;
use App\Models\Interaction;
use App\Models\MerchantOffer;
use App\Services\PointService;
use App\Traits\QueryBuilderTrait;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class MerchantOfferController extends Controller
{
    use QueryBuilderTrait;

    protected $pointService;

    public function __construct()
    {
        $this->pointService = new PointService();
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
        $query->where(function ($query) {
            $query->where('available_at', '<=', now())
                ->where('available_until', '>=', now())
                ->orWhere('available_at', '>=', now());
        });
        // order by latest first if no query sort order
        if (!$request->has('sort')) {
            $query->orderBy('created_at', 'desc');
        }

        $this->buildQuery($query, $request);
        $data = $query->paginate(config('app.paginate_per_page'));
        return MerchantOfferResource::collection($data);
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
    public function show(MerchantOffer $merchantOffer)
    {
        return new MerchantOfferResource($merchantOffer);
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
     * @response scenario=success {
     * "message": "Offer Claimed"
     * }
     * @response scenario=insufficient_point_balance {
     * "message": "Insufficient Point Balance"
     * }
     * @response scenario=offer_no_longer_valid {
     * "message": "Offer is no longer valid"
     * }
     */
    public function postClaimOffer(Request $request)
    {
        $request->validate([
            'offer_id' => 'required|integer|exists:merchant_offers,id',
            'quantity' => 'required|integer|min:1'
        ]);

        // check offer is still valid by checking available_at and available_until
        $offer = MerchantOffer::where('id', request()->offer_id)
            ->published()
            ->with('merchant', 'merchant.user', 'store', 'claims')
            ->where('available_at', '<=', now())
            ->where('available_until', '>=', now())
            ->first();

        if (!$offer) {
            return response()->json([
                'message' => 'Offer is no longer valid'
            ], 422);
        }

        // calculate net amount
        // TODO: future need add in discount, tax etc.
        $net_amount = $offer->unit_price * $request->quantity;

        // ensure user have enough point balance
        $user = request()->user();
        if ($user->point_balance < $net_amount) {
            return response()->json([
                'message' => 'Insufficient Point Balance'
            ], 422);
        }
        try {
            // create claim by deducting user points with PointLedger and create a claim record
            // use attach here, to attach the data with intermediate table.
            // here mean, offer claim by the user with the offer data (pivots).
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

            // fire event
            event(new MerchantOfferClaimed($offer, $user));
        } catch (\Exception $e) {
            Log::error($e->getMessage(), [
                'offer_id' => $offer->id,
                'user_id' => $user->id,
                'quantity' => $request->quantity,
                'unit_price' => $offer->unit_price,
                'total' => $offer->unit_price * $request->quantity,
            ]);

            return response()->json([
                'message' => 'Failed to claim'
            ], 422);
        }

        // refresh offer with latest data
        $offer->refresh();
        return response()->json([
            'message' => 'Claimed successfully',
            'offer' => new MerchantOfferResource($offer)
        ], 200);
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
        })->published()
            ->whereDoesntHave('hiddenUsers', function ($query) {
                $query->where('user_id', auth()->user()->id);
            });

        $this->buildQuery($query, $request);

        $data = $query->with('user', 'interactions', 'media', 'categories')
            ->paginate(config('app.paginate_per_page'));

        return MerchantOfferResource::collection($data);
    }
}
