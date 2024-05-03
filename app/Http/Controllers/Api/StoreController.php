<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\LocationResource;
use App\Http\Resources\RatingCategoryResource;
use App\Http\Resources\StoreRatingResource;
use App\Http\Resources\StoreResource;
use App\Models\Merchant;
use App\Models\RatingCategory;
use App\Models\Store;
use Illuminate\Http\Request;

class StoreController extends Controller
{
    /**
     * Get Stores
     *
     * @param Request $request
     * @return JsonResponse
     *
     * @group Stores
     * @urlParam categories_id string optional Categories ID. Example: 1,2,3
     * @urlParam merchant_ids string optional Merchant IDs. Example: 1,2,3
     * @urlParam store_ids string optional Store IDs. Example: 1,2,3
     * @urlParam limit integer optional Per Page Limit. Example: 10
     *
     * @response scenario=success {
     * "current_page": 1,
     * "data": []
     * }
     */
    public function index(Request $request)
    {
        // only approve merchant, the store can be queried
        $query = Store::whereHas('merchant', function ($q) {
            $q->where('status', Merchant::STATUS_APPROVED);
        })->when($request->has('categories_id'), function ($q) use ($request) {
            $categories = explode(',', $request->input('categories_id'));
            $q->whereHas('categories', function ($q) use ($categories) {
                $q->whereIn('merchant_categories.id', $categories);
            });
        })->when($request->has('merchant_ids'), function ($q) use ($request) {
            $merchantIds = explode(',', $request->input('merchant_ids'));
            $q->whereHas('merchant', function ($q) use ($merchantIds) {
                $q->whereIn('merchants.id', $merchantIds);
            });
        })->when($request->has('store_ids'), function ($q) use ($request) {
            $storeIds = explode(',', $request->input('store_ids'));
            $q->whereIn('id', $storeIds);
        });

        // with merchant, ratings, location
        $query->with(['merchant', 'storeRatings', 'location', 'categories']);

        $stores = $query->paginate($request->input('limit', 10));

        return StoreResource::collection($stores);
    }

    /**
     * Get Store Locations by Store IDS
     *
     * @param Request $request
     * @return JsonResponse
     *
     * @group Stores
     * @urlParam store_ids string required Store IDs. Example: 1,2,3
     *
     * @response scenario=success {
     * "current_page": 1,
     * "data": []
     * }
     */
    public function getStoresLocationsByStoreId(Request $request)
    {
        $this->validate($request, [
            'store_ids' => 'required',
        ]);

        $storeIds = explode(',', $request->store_ids);

        $locations = Store::whereIn('id', $storeIds)->location()->get();

        return LocationResource::collection($locations);
    }

    /**
     * Get Stores Ratings
     *
     * @param Store $store
     * @param Request $request
     * @return JsonResponse
     *
     * @group Stores
     *
     * @urlParam store required Store ID. Example:1
     * @urlParam only_mine boolean optional Only show my ratings. Example:true
     * @urlParam user_id integer optional Only load specific user ratings. Example: 1
     * @urlParam merchant_id integer optional Only load specific merchant ratings. Example: 1
     * @urlParam sort string Column to Sort. Example: Sortable columns are: id, rating, created_at, updated_at
     * @urlParam order string Direction to Sort. Example: Sortable directions are: asc, desc
     * @urlParam limit integer Per Page Limit Override. Example: 10
     *
     *
     * @response scenario=success {
     * "current_page": 1,
     * "data": []
     * }
     */
    public function getRatings(Store $store, Request $request)
    {
        $query = $store->storeRatings()->with(['user' => function ($query) use ($store) {
            $query->withCount(['storeRatings' => function ($query) use ($store) {
                $query->where('store_id', $store->id);
            }]);
        }, 'interactions']);

        // if there's no sort, sort by latest
        if (!$request->has('sort')) {
            $request->merge(['sort' => 'created_at']);
            $request->merge(['order' => 'desc']);
        }

        // if request has only_mine
        if ($request->has('only_mine')) {
            $query->where('user_id', auth()->id());
        }

        if ($request->has('user_id')) {
            $query->where('user_id', $request->user_id);
        }

        if ($request->has('store_id')) {
            $query->where('store_id', $request->store_id);
        }

        // if dont have user_id and store_id, distinct user_id
        if (!$request->has('user_id') && !$request->has('store_id')) {
            // get only one latest rating per user
            $query->distinct('user_id')->latest('created_at');
        }

        $this->buildQuery($query, $request);

        // with count likes and dislikes
        $query->withCount(['likes', 'dislikes']);

        $results = $query->paginate($request->has('limit') ? $request->limit : config('app.paginate_per_page'));

        return StoreRatingResource::collection($results);
    }

    /**
     * Rate a Store
     *
     * @param Store $store
     * @param Request $request
     * @return JsonResponse
     *
     * @group Stores
     * @urlParam store required Store ID. Example:1
     * @bodyParam rating integer required Rating. Example:5
     * @bodyParam comment string optional Comment. Example:Good service
     * @bodyParam rating_category_ids string optional Rating Category IDs. Example:1,2,3
     *
     * @response scenario=success {
     * data: {}
     * }
     */
    public function postRatings(Store $store, Request $request)
    {
        $request->validate([
            'rating' => 'required|numeric|min:1|max:5',
            // 'rating_category_ids' => 'required',
            'comment' => 'nullable|string',
        ]);

        $rating = $store->storeRatings()->create([
            'user_id' => auth()->id(),
            'rating' => $request->rating,
            'comment' => $request->comment,
        ]);

        if ($request->has('rating_category_ids')) {
            // explode rating categories ids
            $categories = explode(',', $request->rating_category_ids);
            // attach to rating
            $rating->ratingCategories()->attach($categories, ['user_id' => auth()->id()]);
        }

        // consolidate store ratings
        $store->ratings = $store->storeRatings()->avg('rating');
        $store->save();

        // with count likes and dislikes
        $rating->loadCount(['likes', 'dislikes']);

        // load user
        $rating->load('user');

        return new StoreRatingResource($rating);
    }

    /**
     * Get Merchant Menus via Store
     *
     * @param Store $store
     * @return JsonResponse
     *
     * @group Stores
     * @urlParam store required Store ID. Example:1
     *
     * @response scenario=success {
     * [
     * {'name': 'Menu 1', 'url': 'http://example.com/menu1.jpg'},
     * ]
     */
    public function getMerchantMenus(Store $store)
    {
        // get merchant from store
        $merchant = $store->merchant;

        if ($merchant) {
            $menus = $merchant->getMedia(Merchant::MEDIA_COLLECTION_MENUS)->map(function ($item) {
                return [
                    'name' => $item->custom_properties['name'],
                    'url' => $item->getFullUrl()
                ];
            });
        }

        return response()->json($menus);
    }

    /**
     * Get Store Rating Categories
     *
     * @return JsonResponse
     *
     * @group Stores
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
