<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Merchant;
use App\Models\MerchantCategory;
use App\Models\RatingCategory;
use App\Models\Store;

use App\Models\User;
use App\Models\MerchantOfferCampaign;
use App\Models\MerchantOfferVoucher;
use App\Models\MerchantOffer;
use App\Models\MerchantOfferClaim;
use App\Models\View;
use App\Traits\QueryBuilderTrait;

use App\Http\Resources\SyncMerchantResource;
use App\Http\Resources\SyncMerchantCategoryResource;
use App\Http\Resources\SyncMerchantCampaignResource;
use App\Http\Resources\SyncMerchantOfferResource;
use App\Http\Resources\SyncStoreResource;

use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class SyncMerchantPortalController extends Controller
{
    use QueryBuilderTrait;

    /**
     * Get Merchant Categories
     * Merchant portal will call this api to sync data
     */
    public function merchant_categories(Request $request)
    {
        $results =  MerchantCategory::orderBy('id', 'asc')->get();//->paginate(1000);
        return SyncMerchantCategoryResource::collection($results);
    }

    /**
     * Get Merchants Lists
     * Merchant portal will call this api to sync data
     * Get merchant info, logo, user, categories, stores and store categories
     */
    public function merchants(Request $request)
    {   
        $offset = $request->input('offset', 0); // Default offset is 0
        $limit  = $request->input('limit', 100); // Default limit is 100
        $results =  Merchant::orderBy('id', 'asc')
            ->skip($offset)
            ->take($limit)
            ->get();
        return SyncMerchantResource::collection($results);
    }

    /**
     * Get Single Merchant
     * Merchant portal will call this api to sync data
     * Get merchant info, logo, user, categories, stores and store categories
     */
    public function merchant($merchant_id)
    {
        $results = Merchant::where('id', $merchant_id)->first();
        return new SyncMerchantResource($results);
    }

    /**
     * Get Single Store
     * Merchant portal will call this api to sync data
     * Get store info
     */
    public function store($store_id)
    {
        $results = Store::where('id', $store_id)->first();
        return new SyncStoreResource($results);
    }

    /**
     * Post Merchant Registration
     * Merchant portal send data to this api to register the merchant information in base portal
     */
    public function merchant_register(Request $request)
    {
        try {
            $request->validate([
                'business_name' => 'required',
                'company_reg_no' => 'required',
                'brand_name' => 'required',
                'business_phone_no' => 'required|max:20',
                'address' => 'required',
                'address_postcode' => 'required|numeric',
                'state_id' => 'required',
                'country_id' => 'required',
                'pic_name' => 'required',
                'pic_designation' => 'required',
                'pic_phone_no' => 'required|max:20',
                'pic_email' => 'required',
                'pic_ic_no' => 'required',
                'authorised_personnel_designation' => 'required',
                'authorised_personnel_name' => 'required',
                'authorised_personnel_ic_no' => 'required'
            ]);

            //  Merchant data
            $merchant_data = [
                'name' => $request->business_name,
                'email' => $request->pic_email,
                'business_name' => $request->business_name, 
                'company_reg_no' => $request->company_reg_no,
                'brand_name' => $request->brand_name,
                'business_phone_no' => $request->business_phone_no,
                'address' => $request->address,
                'address_postcode' => $request->address_postcode,
                'state_id' => $request->state_id,
                'country_id' => $request->country_id,
                'pic_name' => $request->pic_name,
                'pic_designation' => $request->pic_designation,
                'pic_ic_no' => $request->pic_ic_no,
                'pic_phone_no' => $request->pic_phone_no,
                'pic_email' => $request->pic_email,
                'authorised_personnel_designation' => $request->authorised_personnel_designation,
                'authorised_personnel_name' => $request->authorised_personnel_name,
                'authorised_personnel_ic_no' => $request->authorised_personnel_ic_no,
                'redeem_code' => $request->redeem_code,
                'default_password' => $request->default_password,
                'status' => Merchant::STATUS_PENDING,
            ];

            $merchant = Merchant::create($merchant_data); 

            return response()->json([
                'error'     => false,
                'message'   => 'Success',
                'data'      => [
                    'merchant_id' => $merchant->id
                ]
            ]);

        }catch (\Exception $e) {
            Log::error('[SyncMerchantPortalController] merchant register api failed: ' . $e->getMessage());
            return response()->json([
                'error'     => true,
                'message'   => $e->getMessage()
            ]);
        }
    }

    /**
     * Post Merchant Update
     * Merchant portal send data to this api to update the merchant information in base portal
     */
    public function merchant_update(Request $request)
    {
        try {
            $request->validate([
                // 'business_name' => 'required',
                // 'company_reg_no' => 'required',//|unique:merchants,company_reg_no,'.$request->id
                // 'brand_name' => 'required',
                // 'business_phone_no' => 'required|max:20',
                // 'address' => 'required',
                // 'address_postcode' => 'required|numeric',
                // 'state_id' => 'required',
                // 'country_id' => 'required',
                'pic_name' => 'required',
                'pic_designation' => 'required',
                'pic_phone_no' => 'required|max:20',
                'pic_email' => 'required',
                'pic_ic_no' => 'required',
                // 'authorised_personnel_designation' => 'required',
                // 'authorised_personnel_name' => 'required',
                // 'authorised_personnel_ic_no' => 'required',
                'id' => 'required',
                // 'categories' => 'required'
            ]);

            $merchant = Merchant::findOrFail($request->id);
            if($merchant){
                //  Merchant data
                $merchant_data = [
                    // 'business_name' => $request->business_name, 
                    // 'company_reg_no' => $request->company_reg_no,
                    // 'brand_name' => $request->brand_name,
                    // 'business_phone_no' => $request->business_phone_no,
                    // 'address' => $request->address,
                    // 'address_postcode' => $request->address_postcode,
                    // 'state_id' => $request->state_id,
                    // 'country_id' => $request->country_id,
                    'pic_name' => $request->pic_name,
                    'pic_designation' => $request->pic_designation,
                    'pic_ic_no' => $request->pic_ic_no,
                    'pic_phone_no' => $request->pic_phone_no,
                    'pic_email' => $request->pic_email,
                    // 'authorised_personnel_designation' => $request->authorised_personnel_designation,
                    // 'authorised_personnel_name' => $request->authorised_personnel_name,
                    // 'authorised_personnel_ic_no' => $request->authorised_personnel_ic_no,
                ];

                $merchant->update($merchant_data); 

                //  Process the category change if it not empty
                if(!empty($request->categories)){
                    $merchant->categories()->sync($request->categories);
                }

                return response()->json([
                    'error'     => false,
                    'message'   => 'Success'
                ]);
            }else{
                return response()->json([
                    'error'     => true,
                    'message'   => 'Failed to find the merchant in system.'
                ]);
            }

        }catch (\Exception $e) {
            Log::error('[SyncMerchantPortalController] merchant update api failed: ' . $e->getMessage());
            return response()->json([
                'error'     => true,
                'message'   => $e->getMessage()
            ]);
        }
    }

    /**
     * Post Merchant Update Logo
     * Merchant portal send data to this api to update the merchant information in base portal
     */
    public function merchant_update_logo(Request $request)
    {
        try {
            // Validate input data
            $request->validate([
                'media' => 'required|array',
                'media.model_type' => 'required|string',
                'media.model_id' => 'required|integer',
                'media.uuid' => 'required|string',
                'media.collection_name' => 'required|string',
                'media.name' => 'required|string',
                'media.file_name' => 'required|string',
                'media.mime_type' => 'required|string',
                'media.disk' => 'required|string',
                'media.size' => 'required|integer',
                'media.custom_properties' => 'nullable|array',
                'media.manipulations' => 'nullable|array',
                'media.responsive_images' => 'nullable|array',
                'media.original_url' => 'required|url',
            ]);

            $mediaData = $request->media;

            // Ensure critical fields are not empty
            if (empty($mediaData['original_url']) || empty($mediaData['collection_name'])) {
                Log::warning("Invalid media data: " . json_encode($mediaData));
                return response()->json([
                    'error' => true,
                    'message' => 'Invalid media data',
                ]);
            }

            // Find the merchant
            $merchant = Merchant::find($mediaData['model_id']);
            if (!$merchant) {
                Log::warning("Merchant not found for Media: " . json_encode($mediaData));
                return response()->json([
                    'error' => true,
                    'message' => 'Merchant not found for Media',
                ]);
            }
            
            $merchant->media()->where('collection_name', $mediaData['collection_name'])->delete();
            // Upload media
            $merchant
                ->addMediaFromUrl($mediaData['original_url'])
                ->usingName($mediaData['name'])
                ->usingFileName($mediaData['file_name'])
                ->withCustomProperties($mediaData['custom_properties'] ?? [])
                ->toMediaCollection($mediaData['collection_name'], $mediaData['disk']);

            return response()->json([
                'error' => false,
                'message' => 'Success',
            ]);

        } catch (\Throwable $e) {
            // Log errors and return response
            Log::error("Error in merchant_update_logo: " . $e->getMessage());
            return response()->json([
                'error' => true,
                'message' => 'Error uploading media: ' . $e->getMessage(),
            ]);
        }
    }

    /**
     * Get Merchant's Dashboard
     * Merchant portal will call this api for reporting overview
     */
    public function dashboard(Request $request)
    {
        try {
            $request->validate([
                'merchant_id' => 'required',
            ]);

            //  Get this merchant and update
            $merchant = Merchant::find($request->merchant_id);
            if($merchant){
                $userId = $merchant->user_id;

                //  Total Reviews for all stores under this merchant
                $reviews = $merchant->stores->sum(function ($store) {
                    return $store->storeRatings->count();
                });

                //  Get the average rating for all stores under this merchant
                $ratings = $merchant->stores->sum(function ($store) {
                    return $store->storeRatings->avg('rating');
                });
                $averageRating = $ratings > 0 ? $ratings / count($merchant->stores->pluck('id')) : 0;

                //  Stores Views
                $storesView = View::where('viewable_type', Store::class)
                    ->whereIn('viewable_id', $merchant->stores->pluck('id'))
                    ->count();

                $storesUniqueView = View::where('viewable_type', Store::class)
                    ->whereIn('viewable_id', $merchant->stores->pluck('id'))
                    ->distinct('user_id')
                    ->count('user_id');

                //  Offer Views
                $offerView = View::where('viewable_type', MerchantOffer::class)
                    ->whereIn('viewable_id', $merchant->stores->pluck('id'))
                    ->count('user_id');

                $offerUniqueView = View::where('viewable_type', MerchantOffer::class)
                    ->whereIn('viewable_id', $merchant->stores->pluck('id'))
                    ->distinct('user_id')
                    ->count('user_id');
               
                $data = [
                    'rating'            => (float) number_format($averageRating, 1),
                    'review'            => $reviews,
                    'storesView'        => $storesView,
                    'storesUniqueView'  => $storesUniqueView,
                    'offerView'         => $offerView,
                    'offerUniqueView'   => $offerUniqueView
                ];

                return response()->json([
                    'error'     => false,
                    'message'   => 'Success',
                    'data'      => $data
                ]);

            }else{
                return response()->json([
                    'error'     => true,
                    'message'   => 'Oops! invalid request.'
                ]);
            }

        } catch (\Exception $e) {
            Log::error('[SyncMerchantPortalController] get offer overview api failed: ' . $e->getMessage());
            return response()->json([
                'error'     => true,
                'message'   => $e->getMessage()
            ]);
        }
    }

    /**
     * Get Merchant's Offer Overview
     * Merchant portal will call this api for reporting overview
     */
    public function offer_overview(Request $request)
    {
        try {
            $request->validate([
                'merchant_id' => 'required',
            ]);

            //  Get this merchant and update
            $merchant = Merchant::find($request->merchant_id);
            if($merchant){
                $userId = $merchant->user_id;

                //  Total Vouchers
                $total      = MerchantOfferVoucher::whereHas('merchant_offer', function ($q) use ($userId) {
                    $q->where('user_id', $userId);//->where('status', 1);
                })->count();

                //  Total Purchased
                $sold       = MerchantOfferVoucher::whereHas('merchant_offer', function ($q) use ($userId) {
                    $q->where('user_id', $userId);//->where('status', 1);
                })->whereHas('latestSuccessfulClaim')->whereNotNull('owned_by_id')->count();

                //  Total Redeemded
                $redeemed   = MerchantOfferVoucher::whereHas('merchant_offer', function ($q) use ($userId) {
                    $q->where('user_id', $userId);//->where('status', 1);
                })->whereHas('redeem')->count();

                $data = [
                    'total'     => $total,
                    'redeemed'  => $redeemed,
                    'sold'      => $sold
                ];

                return response()->json([
                    'error'     => false,
                    'message'   => 'Success',
                    'data'      => $data
                ]);

            }else{
                return response()->json([
                    'error'     => true,
                    'message'   => 'Oops! invalid request.'
                ]);
            }

        } catch (\Exception $e) {
            Log::error('[SyncMerchantPortalController] get offer overview api failed: ' . $e->getMessage());
            return response()->json([
                'error'     => true,
                'message'   => $e->getMessage()
            ]);
        }
    }

    /**
     * Get Merchant's Offer lists
     * Merchant portal will call this api for reporting
     */
    public function offer_lists(Request $request)
    {
        try {
            $request->validate([
                'merchant_id' => 'required',
            ]);

            //  Get this merchant and update
            $merchant = Merchant::find($request->merchant_id);
            if($merchant){
                $userId = $merchant->user_id;

                // $lists = MerchantOfferVoucher::whereHas('merchant_offer', function ($q) use ($userId) {
                //     $q->where('user_id', $userId);
                // })
                // ->whereHas('claim')
                // ->whereNotNull('owned_by_id') 
                // ->with([
                //     'claim',
                //     'latestSuccessfulClaim',
                //     'redeem'
                // ])
                // ->get();

                $lists = DB::table('merchant_offer_vouchers')
                    ->join('merchant_offers', 'merchant_offers.id', '=', 'merchant_offer_vouchers.merchant_offer_id')
                    ->join('merchant_offer_user', function ($join) {
                        $join->on('merchant_offer_user.voucher_id', '=', 'merchant_offer_vouchers.id')
                            ->whereColumn('merchant_offer_user.user_id', '=', 'merchant_offer_vouchers.owned_by_id');
                    })
                    ->leftJoin('merchant_offer_claims_redemptions', 'merchant_offer_claims_redemptions.claim_id', '=', 'merchant_offer_user.id')
                    ->where('merchant_offers.user_id', $userId)
                    //  Purchases success
                    ->where('merchant_offer_user.status', '=', MerchantOfferClaim::CLAIM_SUCCESS)
                    ->whereNotNull('merchant_offer_vouchers.owned_by_id')
                    ->select(
                        'merchant_offer_vouchers.id as id',
                        'merchant_offer_vouchers.code as code',
                        'merchant_offer_vouchers.voided as voided',
                        'merchant_offers.name as offer_name',
                        'merchant_offers.expiry_days as expiry_days',
                        DB::raw('MAX(merchant_offer_user.created_at) as purchased_at'),
                        DB::raw('CASE WHEN merchant_offer_claims_redemptions.id IS NOT NULL THEN 1 ELSE 0 END as isRedeemed'),
                        DB::raw('CASE WHEN merchant_offer_claims_redemptions.id IS NOT NULL THEN merchant_offer_claims_redemptions.created_at ELSE "" END as redeemed_at'),
                        // 'merchant_offers.description as merchant_offer_description',
                        // 'merchant_offer_user.order_no as order_no',
                        // 'merchant_offer_user.transaction_no as transaction_no',
                        // 'merchant_offer_user.status as claimStatus',
                        
                        // DB::raw('(SELECT created_at 
                        //       FROM merchant_offer_user 
                        //       WHERE voucher_id = merchant_offer_vouchers.id 
                        //         AND status = ' . MerchantOfferClaim::CLAIM_SUCCESS . ' 
                        //       ORDER BY created_at DESC 
                        //       LIMIT 1) as purchased_at')
                    )
                    ->groupBy(
                        'merchant_offer_vouchers.id',
                        'merchant_offer_vouchers.code',
                        'merchant_offer_vouchers.voided',
                        'merchant_offers.name',
                        'merchant_offers.expiry_days',
                        'merchant_offer_claims_redemptions.id',
                        'merchant_offer_claims_redemptions.created_at'
                    )
                    ->distinct()
                    ->get();

                return response()->json([
                    'error'     => false,
                    'message'   => "Success",
                    'data'      => $lists
                ]);

            }else{
                return response()->json([
                    'error'     => true,
                    'message'   => 'Oops! invalid request.'
                ]);
            }

        } catch (\Exception $e) {
            Log::error('[SyncMerchantPortalController] get offer lists api failed: ' . $e->getMessage());
            return response()->json([
                'error'     => true,
                'message'   => $e->getMessage()
            ]);
        }
    }
    
     /**
     * Get Merchant's Offer Codes lists
     * Merchant portal will call this api for reporting
     */
    public function offer_code_lists(Request $request)
    {
        try {
            $request->validate([
                'merchant_id' => 'required',
            ]);

            //  Get this merchant and update
            $merchant = Merchant::find($request->merchant_id);
            if($merchant){
                $userId = $merchant->user_id;

                $lists = DB::table('merchant_offer_vouchers')
                    ->join('merchant_offers', 'merchant_offers.id', '=', 'merchant_offer_vouchers.merchant_offer_id')
                    ->leftJoin('merchant_offer_user', 'merchant_offer_user.voucher_id', '=', 'merchant_offer_vouchers.id')
                    ->leftJoin('merchant_offer_claims_redemptions', 'merchant_offer_claims_redemptions.claim_id', '=', 'merchant_offer_user.id')
                    ->where('merchant_offers.user_id', $userId)
                    ->select(
                        'merchant_offer_vouchers.id as id',
                        'merchant_offer_vouchers.code as code',
                        'merchant_offers.name as offer_name',
                        DB::raw('COALESCE(merchant_offer_user.status, 0) as purchase_status'), 
                        DB::raw('CASE WHEN merchant_offer_claims_redemptions.id IS NOT NULL THEN 1 ELSE 0 END as isRedeemed')
                    )
                    ->groupBy(
                        'merchant_offer_vouchers.id',
                        'merchant_offer_vouchers.code',
                        'merchant_offers.name',
                        'merchant_offer_user.status',
                        'merchant_offer_claims_redemptions.id'
                    )
                    ->distinct()
                    ->get();

                return response()->json([
                    'error'     => false,
                    'message'   => "Success",
                    'data'      => $lists
                ]);

            }else{
                return response()->json([
                    'error'     => true,
                    'message'   => 'Oops! invalid request.'
                ]);
            }

        } catch (\Exception $e) {
            Log::error('[SyncMerchantPortalController] get offer lists api failed: ' . $e->getMessage());
            return response()->json([
                'error'     => true,
                'message'   => $e->getMessage()
            ]);
        }
    }

    // /**
    //  * Get Merchant's campaign
    //  */
    // public function campaigns(Request $request)
    // {
    //     try {
    //         $request->validate([
    //             'merchant_id' => 'required',
    //         ]);

    //         //where('status', 1)->
    //         $campaigns = MerchantOfferCampaign::where('user_id', function($query) use ($request) {
    //             $query->select('user_id')
    //                   ->from('merchants')
    //                   ->where('id', $request->merchant_id)
    //                   ->limit(1); // Ensures only one user_id is returned
    //         })->paginate(1000);
    
    //         return SyncMerchantCampaignResource::collection($campaigns);

    //     } catch (\Exception $e) {
    //         Log::error('[SyncMerchantPortalController] get campaigns api failed: ' . $e->getMessage());
    //         return response()->json([
    //             'error'     => true,
    //             'message'   => $e->getMessage()
    //         ]);
    //     }
    // }
    
}
