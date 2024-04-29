<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\Article;
use App\Http\Resources\MerchantRatingResource;
use App\Http\Resources\MerchantResource;
use App\Http\Resources\RatingCategoryResource;
use App\Models\Merchant;
use App\Models\MerchantOffer;
use App\Models\RatingCategory;
use App\Models\Store;
use App\Traits\QueryBuilderTrait;
use Illuminate\Http\Request;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx\Rels;

class MerchantController extends Controller
{
    use QueryBuilderTrait;
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

        $query->with('categories', 'offers', 'stores', 'stores.location');

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

    /**
     * Get Merchant Ratings
     *
     * @param Merchant $merchant
     * @param Request $request
     * @return JsonResponse
     *
     * @group Merchant
     * @urlParam merchant required Merchant ID. Example:1
     * @urlParam only_mine boolean optional Only show my ratings. Example:true
     * @urlParam sort string Column to Sort. Example: Sortable columns are: id, rating, created_at, updated_at
     * @urlParam order string Direction to Sort. Example: Sortable directions are: asc, desc
     * @urlParam limit integer Per Page Limit Override. Example: 10
     *
     * @response scenario=success {
     * "current_page": 1,
     * "data": []
     * }
     */
    public function getRatings(Merchant $merchant, Request $request)
    {
        $query = $merchant->merchantRatings()->with(['user' => function ($query) use ($merchant) {
            $query->withCount(['merchantRatings' => function ($query) use ($merchant) {
                $query->where('merchant_id', $merchant->id);
            }]);
        }]);

        // if there's no sort, sort by latest
        if (!$request->has('sort')) {
            $request->merge(['sort' => 'created_at']);
            $request->merge(['order' => 'desc']);
        }

        // get only one latest rating per user
        $query->distinct('user_id')->latest('created_at');

        // if request has only_mine
        if ($request->has('only_mine')) {
            $query->where('user_id', auth()->id());
        }

        $this->buildQuery($query, $request);

        $results = $query->paginate($request->has('limit') ? $request->limit : config('app.paginate_per_page'));

        return MerchantRatingResource::collection($results);
    }

    /**
     * Rate a Merchant
     *
     * @param Merchant $merchant
     * @param Request $request
     * @return JsonResponse
     *
     * @group Merchant
     * @urlParam merchant required Merchant ID. Example:1
     * @bodyParam rating integer required Rating. Example:5
     * @bodyParam comment string optional Comment. Example:Good service
     * @bodyParam rating_category_ids string required Rating Category IDs. Example:1,2,3
     *
     * @response scenario=success {
     * data: {}
     * }
     */
    public function postRatings(Merchant $merchant, Request $request)
    {
        $request->validate([
            'rating' => 'required|numeric|min:1|max:5',
            'rating_category_ids' => 'required',
            'comment' => 'nullable|string',
        ]);

        $rating = $merchant->merchantRatings()->create([
            'user_id' => auth()->id(),
            'rating' => $request->rating,
            'comment' => $request->comment,
        ]);

        // explode rating categories ids
        $categories = explode(',', $request->rating_category_ids);
        // attach to rating
        $rating->ratingCategories()->attach($categories, ['user_id' => auth()->id()]);

        // consolidate merchant ratings
        $merchant->ratings = $merchant->merchantRatings()->avg('rating');
        $merchant->save();

        return new MerchantRatingResource($rating);
    }

    /**
     * Get Merchant Menus
     *
     * @param Merchant $merchant
     * @return JsonResponse
     *
     * @group Merchant
     * @urlParam merchant required Merchant ID. Example:1
     *
     * @response scenario=success {
     * [
     * 'https://example.com/menu1.pdf',
     * 'https://example.com/menu2.pdf',
     * 'https://example.com/menu3.pdf',
     * ]
     */
    public function getMerchantMenus(Merchant $merchant)
    {
        $menus = $merchant->getMedia(Merchant::MEDIA_COLLECTION_MENUS)->map(function ($item) {
            return $item->getFullUrl();
        });

        return response()->json($menus);
    }

    /**
     * Get Merchant Rating Categories
     *
     * @return JsonResponse
     *
     * @group Merchant
     *
     * @response scenario=success {
     * data: []
     * }
     */
    public function getRatingCategories()
    {
        $ratingCategories = RatingCategory::all();

        return RatingCategoryResource::collection($ratingCategories);
    }
}
