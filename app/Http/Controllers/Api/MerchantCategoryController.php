<?php

namespace App\Http\Controllers\Api;

use App\Models\MerchantCategory;
use App\Http\Controllers\Controller;
use App\Http\Resources\MerchantCategoryResource;
use Illuminate\Http\Request;
use App\Traits\QueryBuilderTrait;

class MerchantCategoryController extends Controller
{
    use QueryBuilderTrait;

    /**
     * Get popular Merchant Categories
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     *
     * @group Merchant
     * @subgroup Merchant Categories
     * @bodyParam is_featured integer Is Featured Categories. Example: 1
     * @bodyParam filter string Column to Filter. Example: Filterable columns are: id, name, created_at, updated_at
     * @bodyParam filter_value string Value to Filter. Example: Filterable values are: 1, 2, 3, 4, 5, 6, 7, 8, 9, 10
     * @bodyParam sort string Column to Sort. Example: Sortable columns are: id, name, created_at, updated_at
     * @bodyParam order string Direction to Sort. Example: Sortable directions are: asc, desc
     * @bodyParam limit integer Per Page Limit Override. Example: 10
     * @bodyParam offset integer Offset Override. Example: 0
     * @response scenario=success {
     * "categories": []
     * }
     * @response status=404 scenario="Not Found"
     */
    public function index(Request $request)
    {
        // get popular tags by offer count
        $query = MerchantCategory::withCount('offer');

        // get is_featured only
        if ($request->has('is_featured') && $request->is_featured == 1) {
            $query->where('is_featured', $request->is_featured);
        }

        // if there's no sort order then default sort by offer count
        if (!$request->has('sort')) {
            $query->orderBy('offer_count', 'desc');
        }

        $this->buildQuery($query, $request);

        $categories = $query->paginate(($request->has('limit')) ? $request->limit : config('app.paginate_per_page'));

        return MerchantCategoryResource::collection($categories);
    }

    /**
     * Get Merchant Categories by offer id
     *
     * @param $offer_id integer
     * @return \Illuminate\Http\JsonResponse
     *
     * @group Merchant
     * @subgroup Merchant Categories
     * @urlParam offer_id integer required The id of the merchant offer id. Example: 1
     * @response scenario=success {
     * "categories": []
     * }
     * @response status=404 scenario="Not Found"
     */
    public function getMerchantCategoryByOfferId($offer_id)
    {
        $merchantOfferCategories = MerchantCategory::whereHas('offer', function ($query) use ($offer_id) {
            $query->where('offer_id', $offer_id);
        })->paginate(config('app.paginate_per_page'));

        return MerchantCategoryResource::collection($merchantOfferCategories);
    }
}
