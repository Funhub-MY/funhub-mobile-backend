<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\LocationResource;
use App\Http\Resources\PublicStoreResource;
use App\Http\Resources\RatingCategoryResource;
use App\Http\Resources\StoreRatingResource;
use App\Http\Resources\StoreResource;
use App\Models\Article;
use App\Models\Location;
use App\Models\LocationRating;
use App\Models\Merchant;
use App\Models\MerchantOffer;
use App\Models\RatingCategory;
use App\Models\ShareableLink;
use App\Models\Store;
use App\Models\User;
use App\Traits\QueryBuilderTrait;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class StoreController extends Controller
{
    use QueryBuilderTrait;

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
        $query = Store::when($request->has('categories_id'), function ($q) use ($request) {
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

            // must order by storeids
            $q->orderBy(DB::raw('FIELD(id, ' . implode(',', $storeIds) . ')'));
            Log::info('Stores Query Load IDs: ' . implode(',', $storeIds));
        });

        // with merchant, ratings, location
        $query->with(['merchant', 'storeRatings', 'location', 'categories', 'media']);

        // with count total ratings
        $query->withCount([
            'storeRatings',
            'availableMerchantOffers',
        ]);

        // with count published merchant offers
        $query->withCount(['merchant_offers' => function ($query) {
            $query->where('merchant_offers.status', MerchantOffer::STATUS_PUBLISHED)
                ->where('merchant_offers.available_at', '<=', now());
        }]);

        $stores = $query->paginate($request->input('limit', 10));


        // modify the paginated results
        $stores->getCollection()->transform(function ($store) {
            // query the articles associated with the store via the shared location
            $articles = Article::whereHas('location', function ($query) use ($store) {
                $query->whereIn('locatables.location_id', function ($query) use ($store) {
                    $query->select('location_id')
                        ->from('locatables')
                        ->where('locatable_type', Store::class)
                        ->where('locatable_id', $store->id);
                });
            })->with(['user.followers' => function ($query) {
                $query->where('user_id', auth()->id());
            }, 'location'])->get();

            $store->setRelation('articles', $articles);

            // store's location ratings same as the number of articles which tagged same location as store
            // due to when creating article need to rate the location if user tagged a location for an article
            $store->location_ratings_count = $articles->count();

            return $store;
        });

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

        $locations = Location::whereHas('stores', function ($q) use ($storeIds) {
            $q->whereIn('stores.id', $storeIds);
        })->get();

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
            }])
            ->where('status', User::STATUS_ACTIVE);
        }, 'interactions', 'ratingCategories']);

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

        // if dont have user_id and only select by distinct
        if (!$request->has('user_id')) {
            // get only one latest rating per user
            $latestRatings = $store->storeRatings()
                ->selectRaw('MAX(id) as id')
                ->groupBy('user_id');

            $query->joinSub($latestRatings, 'latest_ratings', function ($join) {
                $join->on('store_ratings.id', '=', 'latest_ratings.id');
            });
        }

        // with count likes and dislikes
        $query->withCount(['likes', 'dislikes']);

        $this->buildQuery($query, $request);

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

        // load user, ratingCategories
        $rating->load('user', 'ratingCategories');

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
                    'name' => (isset($item->custom_properties['name'])) ? $item->custom_properties['name'] : $item->file_name,
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

    /**
     * Get Top X Store Rating Categories of
     *
     * @param Store $store
     * @return void
     *
     * @group Stores
     * @urlParam store required Store ID. Example:1
     * @urlParam limit integer optional Limit. Example: 3
     * @urlParam only_with_ratings boolean optional Only with ratings. Example: 1
     *
     * @response scenario=success {
     * data: []
     * }
     */
    public function getStoreRatingCategories(Store $store, Request $request)
    {
        $ratingCategories = RatingCategory::withCount(['storeRatings' => function ($query) use ($store) {
            $query->where('store_ratings.store_id', $store->id);
        }])
            ->when($request->has('only_with_ratings') && $request->input('only_with_ratings') === '1', function ($query) {
                $query->whereHas('storeRatings');
            })
            ->orderBy('store_ratings_count', 'desc')
            ->take($request->has('limit') ? $request->limit : 3)
            ->get();

        return RatingCategoryResource::collection($ratingCategories);
    }

    /**
     * Get Stores by Location ID
     *
     * @param Request $request
     * @return JsonResponse
     *
     * @group Stores
     * @urlParam location_id required Location ID. Example:1
     *
     * @response scenario=success {
     * "current_page": 1,
     * "data": []
     * }
     */
    public function getStoreByLocationId(Request $request)
    {
        $this->validate($request, [
            'location_id' => 'required',
        ]);

        $location = Location::where('id', $request->location_id)->first();
        if (!$location) {
            return response()->json(['message' => 'Location not found'], 404);
        }

        $stores = $location->stores()->paginate(config('app.paginate_per_page'));

        return StoreResource::collection($stores);
    }

    public function getPublicStorePublicView(Request $request)
    {
        $this->validate($request, [
            'share_code' => 'required',
        ]);

         // get article by ShareableLink
         $share = ShareableLink::where('link', $request->share_code)
            ->where('model_type', Store::class)
            ->first();

        if (!$share) {
            return abort(404);
        }
        $store = $share->model;

        $store->load('merchant', 'storeRatings', 'location', 'categories', 'media')
            // with limit articles to 6
            ->load(['articles' => function ($query) {
                $query->limit(6);
            }])
            // with limit store ratings to 6
            ->load(['storeRatings' => function ($query) {
                $query->limit(6);
            }])
            // with limit merchant_offers to 6
            ->load(['merchant_offers' => function ($query) {
                $query->limit(6);
            }])
            ->withCount('storeRatings', 'availableMerchantOffers');

        return response()->json([
            'store' => new PublicStoreResource($store)
        ]);
    }
}
