<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\Article;
use App\Http\Resources\MerchantResource;
use App\Models\Merchant;
use App\Models\Store;
use Illuminate\Http\Request;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx\Rels;

class MerchantController extends Controller
{
    /**
     * Get Merchants
     *
     * @param Request $request
     * @return JsonResponse
     *
     * @group Merchant
     * @urlParam search string optional Search by business name. Example:business name
     * @urlParam categories_id string optional Search by categories id. Example:1,2,3
     * @urlParam merchant_ids string optional Search by merchant ids. Example:1,2,3
     * @urlParam store_ids string optional Search by store ids. Example:1,2,3
     * @urlParam limit integer optional Limit the number of results. Example:10
     *
     * @response scenario=success {
     * "current_page": 1,
     * "data": []
     * }
     */
    public function index(Request $request)
    {
        $query = Merchant::approved();

        if ($request->has('search')) {
            $query->where('business_name', 'like', '%' . request('search') . '%');
        }

        if ($request->has('categories_id')) {
            // explode if has comma
            $categories = explode(',', request('categories_id'));
            $query->whereHas('categories', function ($q) use ($categories) {
                $q->whereIn('merchant_categories.id', $categories);
            });
        }

        if ($request->has('merchant_ids')) {
            // explode if has comma
            $merchantIds = explode(',', request('merchant_ids'));
            $query->whereIn('id', $merchantIds);
        }

        if ($request->has('store_ids')) {
            // explode if has comma
            $storeIds = explode(',', request('store_ids'));
            $query->whereHas('stores', function ($q) use ($storeIds) {
                $q->whereIn('stores.id', $storeIds);
            });
        }

        $query->with('categories', 'offers', 'stores');

        $results = $query->paginate($request->has('limit') ? $request->limit : config('app.paginate_per_page'));

        return MerchantResource::collection($results);
    }

    /**
     * Get Nearby Merchants
     * Will lookup based on user provided lat,lng and search for merchant stores near user
     *
     * @param Request $request
     * @return JsonResponse
     *
     * @group Merchant
     * @urlParam lat string required Latitude. Example:3.1390
     * @urlParam lng string required Longitude. Example:101.6869
     * @urlParam radius string optional Radius in km. Example:10
     * @urlParam limit integer optional Limit the number of results. Example:10
     *
     * @response scenario=success {
     * "current_page": 1,
     * "data": []
     * }
     */
    public function getNearbyMerchants(Request $request)
    {
        // query by stores_index form algolia
        // search all stores nearby
        if (!config('app.search_location_use_algolia')) {
            return MerchantResource::collection([]);
        }

        $radius = $request->has('radius') ? $request->radius : config('app.location_default_radius'); // 10km default

        $data = Store::search('')->with([
            'aroundLatLng' => $request->lat . ',' . $request->lng,
            'aroundRadius' => $radius * 1000,
            'aroundPrecision' => 50,
        ]);

        // get all store ids only
        $storeIds = $data->get()->pluck('id')->toArray();

        // get all merchants related to the stores arranged by stores ids sequence
        $merchants = Merchant::whereHas('stores', function ($q) use ($storeIds) {
            $q->whereIn('stores.id', $storeIds);
        })->paginate($request->has('limit') ? $request->limit : config('app.paginate_per_page'));

        return MerchantResource::collection($merchants);
    }
}
