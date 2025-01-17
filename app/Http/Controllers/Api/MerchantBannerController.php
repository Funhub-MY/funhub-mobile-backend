<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\MerchantBannerResource;
use App\Models\MerchantBanner;
use Illuminate\Http\Request;

class MerchantBannerController extends Controller
{
    /**
     * Get Banners
     * 
     * Get a list of published merchant banners ordered by ascending order.
     * 
     * @group Merchant
     * @subgroup Banners
     * 
     * @response scenario=success {
     *  "data": [
     *      {
     *          "id": 1,
     *          "title": "New Year Sale",
     *          "link_to": "https://example.com/sale",
     *          "banner_url": "https://example.com/images/banner.jpg",
     *          "created_at": "2025-01-09T04:25:36.000000Z"
     *      }
     *  ]
     * }
     * 
     * @return \Illuminate\Http\JsonResponse
     */
    public function getBanners()
    {
        $banners = MerchantBanner::where('status', MerchantBanner::STATUS_PUBLISHED)
            ->latest()
            ->get();

        return response()->json([
            'data' => MerchantBannerResource::collection($banners)
        ]);
    }
}
