<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Merchant;
use App\Models\MerchantCategory;
use App\Models\RatingCategory;
use App\Models\Store;
use App\Models\Article;
use App\Models\MerchantUserAutolink;
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
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Cache;

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

            //  Create or Link the User Data due to the User is required
            $user = null;
            $hasAutoLinkedUser = false;
            // create a default password
            $password       = Str::random(8);
            $countryCode    = substr($request->pic_phone_no, 0, 2);
            $phoneNo        = substr($request->pic_phone_no, 2);

            //  Find the user based on country code and phone no
            $user = User::where('phone_no', $phoneNo)
                ->where('phone_country_code', $countryCode)
                ->first();

            //  If found the user, do auto link
            if ($user) {
                $hasAutoLinkedUser = true;
            }

            //  If can't find the user, create the new user
            if (!$user) {
                $user = User::create([
                    'name' => $request->brand_name,
                    'phone_no' => $phoneNo,
                    'phone_country_code' => $countryCode,
                    'password' => bcrypt($password),
                ]);
            }

            //  Assign the user to merchant role (Is this still need?)
            $user->assignRole('merchant');

            //  Merchant data
            $merchant_data = [
                'user_id' => $user->id,
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

            //  Add log for Auto Linked User and create Auto Link
            if ($hasAutoLinkedUser) {
                Log::info('[SyncMerchantPortalController] Auto linked user with phone_no: ' . $request->pic_phone_no);
                MerchantUserAutolink::create([
                    'merchant_id' => $merchant->id,
                    'user_id' => $user->id,
                    'phone_no' => $phoneNo,
                    'phone_country_code' => $countryCode
                ]);
            }   

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
     * Get Merchant's Reports
     * Merchant portal will call this api for reporting overview regarding merchant
     */
    public function merchant_overview(Request $request)
    {
        try {
            $request->validate([
                'merchant_id' => 'required',
            ]);

            //  Get this merchant and update
            $merchant = Merchant::find($request->merchant_id);
            if($merchant){
                $userId     = $merchant->user_id;
                $storeIds   = $merchant->stores->pluck('id');

                //  Total Reviews for all stores under this merchant
                $reviews = $merchant->stores->sum(function ($store) {
                    return $store->storeRatings->count();
                });

                //  Total unique user to give review
                $users = $merchant->stores->sum(function ($store) {
                    return $store->storeRatings->groupBy('user_id')->count();
                });

                //  Get articles under this merchant
                // $articles = $merchant->stores->sum(function ($store) {
                //     return $store->articles->count();
                // });

                //  As frontend display the article is bind with location, so need to get the locations data and find the articles.
                $locationIds = $merchant->stores->pluck('location')->flatten()->pluck('id')->unique();

                $articles = Article::where("status", Article::STATUS_PUBLISHED)
                    ->whereHas('location', function ($q) use ($locationIds) {
                        $q->whereIn('locations.id', $locationIds);
                    })->count();

                //  Get the average rating for all stores under this merchant
                $ratings = $merchant->stores->sum(function ($store) {
                    return $store->storeRatings->avg('rating');
                });
                $averageRating = $ratings > 0 ? $ratings / count($storeIds) : 0;

                //  Stores Views
                $storesView = View::where('viewable_type', Store::class)
                    ->whereIn('viewable_id', $storeIds)
                    ->count();

                $storesUniqueView = View::where('viewable_type', Store::class)
                    ->whereIn('viewable_id', $storeIds)
                    ->distinct('user_id')
                    ->count('user_id');

                $data = [
                    'rating'            => (float) number_format($averageRating, 1),
                    'users'             => $users,
                    'review'            => $reviews,
                    'articles'          => $articles,
                    'storesView'        => $storesView,
                    'storesUniqueView'  => $storesUniqueView
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
            Log::error('[SyncMerchantPortalController] get merchant overview api failed: ' . $e->getMessage());
            return response()->json([
                'error'     => true,
                'message'   => $e->getMessage()
            ]);
        }
    }

    /**
     * Get Merchant's Review lists
     * Merchant portal will call this api for review comment lists
     */
    public function review_lists(Request $request)
    {
        try {
            $request->validate([
                'merchant_id' => 'required',
            ]);

            //  Get this merchant and update
            $merchant = Merchant::find($request->merchant_id);
            if($merchant){
                $userId     = $merchant->user_id;

                // Use Cache::remember to handle caching
                $lists = Cache::remember("merchant_review_list_{$userId}", 60, function () use ($merchant) {
                    $lists = collect();
                    
                    //  Get comments under this merchant
                    $lists = $merchant->stores->load('storeRatings.user', 'storeRatings.ratingCategories')->flatMap(function ($store) {
                        return $store->storeRatings->map(function ($rating) {
                            return [
                                'id' => $rating->id,
                                'comment' => $rating->comment,
                                'rating' => $rating->rating,
                                'created_at' => $rating->created_at,
                                'categories' => $rating->ratingCategories->map(function ($category) {
                                    // Decode JSON to get English translation
                                    $translations = json_decode($category->name_translations, true);
                                    return [
                                        'id' => $category->id,
                                        'name' => $category->name,
                                        'name_en' => $translations['en'] ?? $category->name,
                                    ];
                                }),
                                'user_id' => $rating->user_id,
                                'user_name' => $rating->user->name ?? null, // Avoid error if user is missing
                            ];
                        });
                    });

                    return $lists;
                });

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
            Log::error('[SyncMerchantPortalController] get review lists api failed: ' . $e->getMessage());
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

                //  Get campaign agreement quantity
                $campaigns = MerchantOfferCampaign::where('user_id', $userId)
                    ->select(['id', 'agreement_quantity']) 
                    ->get()->toArray();

                //  Total Vouchers
                $total = 0;
                $voucherTotals = MerchantOfferVoucher::whereHas('merchant_offer', function ($query) use ($userId, $campaigns) {
                    $query->where('user_id', $userId);
                })
                ->select('merchant_offers.merchant_offer_campaign_id as campaign_id', DB::raw('COUNT(*) as total_vouchers'))
                ->join('merchant_offers', 'merchant_offer_vouchers.merchant_offer_id', '=', 'merchant_offers.id')
                ->groupBy('merchant_offers.merchant_offer_campaign_id')
                ->pluck('total_vouchers', 'campaign_id');

                //  Hard solve the total quantity (Get from merchant_offer_voucher) & agreement quantity
                if($campaigns){
                    foreach($campaigns as $campaign){
                        if($campaign['agreement_quantity'] <= 0){
                            $total += empty($voucherTotals[$campaign['id']]) ? 0 : $voucherTotals[$campaign['id']];
                        }else{
                            $total += $campaign['agreement_quantity'];
                        }
                    }
                }

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
                    ->whereNotNull('merchant_offers.merchant_offer_campaign_id')
                    ->select(
                        'merchant_offer_vouchers.id as id',
                        'merchant_offer_vouchers.code as code',
                        'merchant_offer_vouchers.voided as voided',
                        'merchant_offers.name as offer_name',
                        'merchant_offers.expiry_days as expiry_days',
                        DB::raw('MAX(merchant_offer_user.created_at) as purchased_at'),
                        'merchant_offers.fiat_price',
                        'merchant_offers.discounted_point_fiat_price',
                        'merchant_offer_user.purchase_method',
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

                // Use Cache::remember to handle caching
                $lists = Cache::remember("merchant_offer_vouchers_{$userId}", 60, function () use ($userId) {
                    $lists = collect();

                    //  Get campaign agreement quantity
                    $campaigns  = MerchantOfferCampaign::where('user_id', $userId)
                        ->select(['id', 'agreement_quantity']) 
                        ->get()->toArray();

                    if($campaigns){
                        //  Merge the campaign Ids
                        $campaignIds = array_column($campaigns, "id");

                        //  Call the query based on where In
                        $vouchers = DB::table('merchant_offer_vouchers')
                                ->join('merchant_offers', 'merchant_offers.id', '=', 'merchant_offer_vouchers.merchant_offer_id')
                                ->leftJoin('merchant_offer_user', 'merchant_offer_user.voucher_id', '=', 'merchant_offer_vouchers.id')
                                ->leftJoin('merchant_offer_claims_redemptions', 'merchant_offer_claims_redemptions.claim_id', '=', 'merchant_offer_user.id')
                                ->where('merchant_offers.user_id', $userId)
                                ->whereIn('merchant_offers.merchant_offer_campaign_id', $campaignIds)
                                ->select(
                                    'merchant_offer_vouchers.id as id',
                                    'merchant_offer_vouchers.code as code',
                                    'merchant_offers.merchant_offer_campaign_id as campaign_id',
                                    'merchant_offers.name as offer_name',
                                    DB::raw('COALESCE(merchant_offer_user.status, 0) as purchase_status'), 
                                    DB::raw('CASE WHEN merchant_offer_claims_redemptions.id IS NOT NULL THEN 1 ELSE 0 END as isRedeemed')
                                )
                                ->groupBy(
                                    'merchant_offer_vouchers.id',
                                    'merchant_offer_vouchers.code',
                                    'merchant_offers.name',
                                    'merchant_offers.merchant_offer_campaign_id',
                                    'merchant_offer_user.status',
                                    'merchant_offer_claims_redemptions.id'
                                )
                                ->orderBy(DB::raw('CASE WHEN merchant_offer_claims_redemptions.id IS NOT NULL THEN 1 ELSE 0 END'), 'desc')
                                ->orderBy('merchant_offer_vouchers.id', 'asc')
                                ->orderBy('merchant_offers.id', 'asc')
                                ->get();

                        // Iterate through campaigns and filter vouchers for each campaign
                        foreach ($campaigns as $campaign) {
                            // Filter vouchers for this campaign
                            $campaignVouchers = $vouchers->where('campaign_id', $campaign['id']);

                            // Apply limit based on agreement_quantity
                            if ($campaign['agreement_quantity'] > 0) {
                                $campaignVouchers = $campaignVouchers->take($campaign['agreement_quantity']);
                            }

                            // Merge into final list
                            $lists = $lists->merge($campaignVouchers);
                        }
                    }

                    return $lists;
                });
                
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