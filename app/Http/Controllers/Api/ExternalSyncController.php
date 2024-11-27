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
use App\Traits\QueryBuilderTrait;

use App\Http\Resources\ExternalMerchantResource;
use App\Http\Resources\ExternalMerchantCategoryResource;
use App\Http\Resources\ExternalMerchantCampaignResource;
use App\Http\Resources\ExternalMerchantOfferResource;

use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ExternalSyncController extends Controller
{
    use QueryBuilderTrait;

    /**
     * Get Merchant Categories
     */
    public function merchant_categories(Request $request)
    {
        $results =  MerchantCategory::orderBy('id', 'asc');//->paginate(1000);
        return ExternalMerchantCategoryResource::collection($results);
    }

    /**
     * Get Merchants
     * Get merchant info, user, categories, stores and store categories, logo
     */
    public function merchants(Request $request)
    {
        $results =  Merchant::orderBy('id', 'asc');//->paginate(1000);
        return ExternalMerchantResource::collection($results);
    }

    /**
     * Get Merchant's Offer Overview
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
                })->whereHas('redeem')->whereNotNull('owned_by_id')->count();

                //  Total Redeemded
                $redeemed   = MerchantOfferVoucher::whereHas('merchant_offer', function ($q) use ($userId) {
                    $q->where('user_id', $userId);//->where('status', 1);
                })->whereHas('redeem')->count();

                $data = [
                    'total'     => $total,
                    'redeemed'  => $redeemed,
                    'sold'      => $sold
                    //'redeemed'  => $total - $unclaimed
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
            Log::error('[ExternalSyncController] get voucher overview api failed: ' . $e->getMessage());
            return response()->json([
                'error'     => true,
                'message'   => $e->getMessage()
            ]);
        }
    }

    /**
     * Get Merchant's Offer lists
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
                    ->whereNotNull('merchant_offer_vouchers.owned_by_id')
                    ->select(
                        'merchant_offer_vouchers.id as id',
                        'merchant_offer_vouchers.code as code',
                        'merchant_offer_vouchers.voided as voided',
                        'merchant_offers.name as offer_name',
                        // 'merchant_offers.description as merchant_offer_description',
                        // 'merchant_offer_user.order_no as order_no',
                        // 'merchant_offer_user.transaction_no as transaction_no',
                        // 'merchant_offer_user.status as claimStatus',
                        DB::raw('CASE WHEN merchant_offer_claims_redemptions.id IS NOT NULL THEN 1 ELSE 0 END as isRedeemed'),
                        DB::raw('CASE WHEN merchant_offer_claims_redemptions.id IS NOT NULL THEN merchant_offer_claims_redemptions.created_at ELSE "" END as redeemed_at'),
                        DB::raw('(SELECT created_at 
                              FROM merchant_offer_user 
                              WHERE voucher_id = merchant_offer_vouchers.id 
                                AND status = ' . MerchantOfferClaim::CLAIM_SUCCESS . ' 
                              ORDER BY created_at DESC 
                              LIMIT 1) as purchased_at')
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
            Log::error('[ExternalSyncController] get voucher lists api failed: ' . $e->getMessage());
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
    
    //         return ExternalMerchantCampaignResource::collection($campaigns);

    //     } catch (\Exception $e) {
    //         Log::error('[ExternalSyncController] get campaigns api failed: ' . $e->getMessage());
    //         return response()->json([
    //             'error'     => true,
    //             'message'   => $e->getMessage()
    //         ]);
    //     }
    // }
    
}
