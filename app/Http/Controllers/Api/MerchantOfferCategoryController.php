<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Traits\QueryBuilderTrait;
use App\Models\MerchantOfferCategory;
use App\Http\Resources\MerchantOfferCategoryResource;

class MerchantOfferCategoryController extends Controller
{
    /**
     * Get All Merchant Offer Categories
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     *
     * @group Merchant
     * @subgroup Merchant Offer Categories
     * @queryParam is_featured integer Is Featured Categories. Example: 1
     * @queryParam is_active integer Is Active Categories. Example: 0
     * @queryParam id integer Get subcategories of a specific parent category by its ID. Example: 1
     * @bodyParam limit integer Per Page Limit Override. Example: 10
     * @response scenario=success {
     * "data": []
     * }
     */
    public function index(Request $request)
    {
        $query = MerchantOfferCategory::query();

        // Filter by parent category ID if provided
        if ($request->has('id')) {
            $query->where('parent_id', $request->id);
        }

        // Filter by is_active if provided, else query by default is_active is true
        if ($request->has('is_active')) {
            $query->where('is_active', $request->is_active);
        } else {
            $query->active();
        }

        // Filter by is_featured if provided
        if ($request->has('is_featured') && $request->is_featured == 1) {
            $query->where('is_featured', $request->is_featured);
        }

        // Paginate with default or specified limit
        $limit = $request->input('limit', config('app.paginate_per_page'));
        $query->withCount(['merchantOffers', 'availableOffers']);
        $offerCategories = $query->paginate($limit);

        return MerchantOfferCategoryResource::collection($offerCategories);
    }
}
