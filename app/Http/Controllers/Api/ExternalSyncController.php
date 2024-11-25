<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Merchant;
use App\Models\MerchantCategory;
use App\Models\RatingCategory;
use App\Models\Store;
use App\Traits\QueryBuilderTrait;

use App\Http\Resources\ExternalMerchantResource;
use App\Http\Resources\ExternalMerchantCategoryResource;

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
        $results =  MerchantCategory::orderBy('id', 'asc')->paginate(1000);
        return ExternalMerchantCategoryResource::collection($results);
    }

    /**
     * Get Merchants
     * Get merchant info, user, categories, stores and store categories, logo
     */
    public function merchants(Request $request)
    {
        $results =  Merchant::orderBy('id', 'asc')->paginate(1000);
        return ExternalMerchantResource::collection($results);
    }

    /**
     * Get Merchant's campaign
     */
    public function campaigns(Request $request)
    {
        try {
            $request->validate([
                'merchant_id' => 'required',
            ]);

            //  Get this merchant and update
            $campaigns = Campaign::where('is_active', true)->get();

        } catch (\Exception $e) {
            Log::error('[ExternalSyncController] get campaigns api failed: ' . $e->getMessage());
            return response()->json([
                'error'     => true,
                'message'   => $e->getMessage()
            ]);
        }
    }
    
}
