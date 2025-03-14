<?php

namespace App\Http\Controllers\Api;

use App\Events\RatedStore;
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
use App\Models\MerchantOfferClaim;
use App\Models\RatingCategory;
use App\Models\ShareableLink;
use App\Models\Store;
use App\Models\StoreRating;
use App\Models\User;
use App\Traits\QueryBuilderTrait;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
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
     * @urlParam include_unlisted integer optional Include unlisted stores, must have store_ids specified else all will be LISTED store only. Example: 1
     * @urlParam include_listed_unlisted integer optional Include all listed and unlisted stores. Example: 1
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
        });

        // with merchant, ratings, location
        $query->with([
            'merchant',
            'merchant.media',
            'storeRatings' => function ($query) {
                $query->whereHas('user', function ($q) {
                    $q->where('status', '!=', User::STATUS_ARCHIVED);
                });
            },
            'location',
            'location.articles' => function ($query) {
                $query->published()
                    ->public()
                    ->latest();
            },
            'location.articles.media',
            'location.ratings',
            'interactions',
            'categories',
            'parentCategories',
            'media'
        ]);

        // with count total ratings
        $query->withCount([
            'storeRatings as store_ratings_count' => function ($query) {
                $query->whereHas('user', function ($q) {
                    $q->where('status', '!=', User::STATUS_ARCHIVED);
                })
                ->select(DB::raw('COUNT(DISTINCT user_id)'));
            },
            'availableMerchantOffers'
        ]);

        // if have include_unlisted, must have store_ids
        if ($request->has('include_unlisted') && $request->has('store_ids')) {
            if ($request->include_unlisted == 1) {
                $query->where('status', Store::STATUS_INACTIVE);
            }
        } else {
           if ($request->has('include_listed_unlisted') && $request->include_listed_unlisted == 1) {
                // use sub where query to get both active and inactive
                $query->where(function ($query) {
                    $query->where('status', Store::STATUS_ACTIVE)
                        ->orWhere('status', Store::STATUS_INACTIVE);
                });
            } else {
                // only listed stores for main index
                $query->where('status', Store::STATUS_ACTIVE);
            }
        }

        $stores = $query
            ->paginate($request->input('limit', 10));

        $stores->getCollection()->transform(function ($store) {
            // location ratings count is articles count
            $location = $store->location->first();
            if ($location && $location->articles) {
                $store->articles = $location->articles;
                // location ratings count should be by location which has location rating
                $locationRatings = $location->ratings->count();
                $store->location_ratings_count = $locationRatings;
            } else {
                $store->articles = null;
                $store->location_ratings_count = 0;
            }
            return $store;
        });
        return StoreResource::collection($stores);
    }

    /**
     * Get Stores Following Been Here
     *
     * @param Request $request
     * @return JsonResponse
     *
     * @group Stores
     * @urlParam store_ids string required The store ids. Example: 1,2,3
     * @response scenario=success {"data": [{"id": 1, "name": "John Doe"}, {"id": 2, "name": "John Doe"}]}
     */
    public function getStoresFollowingBeenHere(Request $request)
    {
        $this->validate($request, [
            'store_ids' => 'required|string',
        ]);

        $storeIds = explode(',', $request->store_ids);

        $articles = Article::whereHas('location', function ($query) use ($storeIds) {
            $query->whereIn('locatables.location_id', function ($query) use ($storeIds) {
                $query->select('location_id')
                    ->from('locatables')
                    ->where('locatable_type', Store::class)
                    ->whereIn('locatable_id', $storeIds);
            });
        })->with(['user.followers' => function ($query) {
            $query->where('user_id', auth()->id());
        }, 'location.stores', 'media' => function ($query) {
            $query->where('collection_name', Article::MEDIA_COLLECTION_NAME)
                ->orderBy('order_column', 'asc')
                ->take(1);
        }])->get();

        $followingsBeenHere = [];
        $addedUsers = [];

        foreach ($articles as $article) {
            $user = $article->user;
            $isFollowing = $user->followers->contains('id', auth()->id());
            if ($isFollowing) {
                $stores = collect($article->location)->pluck('stores')->flatten(1);
                $articleStoreIds = $stores->pluck('id')->unique();
                foreach ($articleStoreIds as $storeId) {
                    if (!isset($followingsBeenHere[$storeId])) {
                        $followingsBeenHere[$storeId] = [
                            'storeId' => $storeId,
                            'followingsBeenHere' => [],
                        ];
                        $addedUsers[$storeId] = [];
                    }
                    if (!in_array($user->id, $addedUsers[$storeId])) {
                        $followingsBeenHere[$storeId]['followingsBeenHere'][] = [
                            'id' => $user->id,
                            'name' => $user->name,
                            'username' => $user->username,
                            'avatar' => $user->avatar_url,
                            'avatar_thumb' => $user->avatar_thumb_url,
                            'has_avatar' => $user->hasMedia('avatar'),
                        ];
                        $addedUsers[$storeId][] = $user->id;
                    }
                }
            }
        }

        // add the remaining store IDs without "followings been here" data
        foreach ($storeIds as $storeId) {
            if (!isset($followingsBeenHere[$storeId])) {
                $followingsBeenHere[$storeId] = [
                    'storeId' => (int)$storeId,
                    'followingsBeenHere' => [],
                ];
            }
        }

        // sort the data by same order of pass in store_ids
        $data = [];
        foreach ($storeIds as $storeId) {
            if (isset($followingsBeenHere[$storeId])) {
                $data[] = $followingsBeenHere[$storeId];
            }
        }

        return response()->json(['data' => $data]);
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
            ->where('users.status', User::STATUS_ACTIVE);
        }, 'interactions', 'ratingCategories'])
        ->join('users', 'store_ratings.user_id', '=', 'users.id')
        ->where('users.status', User::STATUS_ACTIVE);

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
                ->join('users', 'store_ratings.user_id', '=', 'users.id')
                ->where('users.status', User::STATUS_ACTIVE)
                ->selectRaw('MAX(store_ratings.id) as id')
                ->groupBy('store_ratings.user_id');

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

        // fire event RatedStore
        event(new RatedStore($store, $rating->user));

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

        $menus = null;
        if ($merchant) {
            $menus = $store->getMedia(Store::MEDIA_COLLECTION_MENUS)->map(function ($item) {
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
            $query->where('store_ratings.store_id', $store->id)
                  ->select(DB::raw('COUNT(DISTINCT(store_ratings.user_id))'))
                  ->latest();
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
     * Check if store exists at given location
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     *
     * @group Stores
     * @bodyParam lat float optional Latitude coordinate. Example: 3.1390
     * @bodyParam lng float optional Longitude coordinate. Example: 101.6869
     * @bodyParam location_name string optional Location name to search. Example: "KLCC"
     * 
     * @response scenario=success {
     *   "exists": true,
     *   "store": {
     *     "id": 1,
     *     "name": "Store Name",
     *     ...
     *   }
     * }
     * @response scenario=error {
     *   "message": "Either coordinates (lat, lng) or location_name must be provided"
     * }
     */
    public function getCheckLocationIsExistingStore(Request $request)
    {
        $query = Store::query()
            ->with(['location']);

        if ($request->has(['lat', 'lng'])) {
            // search by coordinates
            $lat = $request->input('lat');
            $lng = $request->input('lng');
            
            $query->whereHas('location', function ($q) use ($lat, $lng) {
                // Using exact match for coordinates
                $q->where('lat', $lat)
                    ->where('lng', $lng);
            });
        } elseif ($request->has('location_name')) {
            // search by location name
            $locationName = $request->input('location_name');
            
            $query->whereHas('location', function ($q) use ($locationName) {
                $q->where('name', 'LIKE', '%' . $locationName . '%');
            });
        } else {
            return response()->json([
                'message' => 'Either coordinates (lat, lng) or location_name must be provided',
            ], 422);
        }

        $store = $query->first();

        return response()->json([
            'exists' => !is_null($store),
            'store' => $store ? new StoreResource($store) : null,
        ]);
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

    /**
     * Get Public Store Public View
     *
     * @param Request $request
     * @return JsonResponse
     */
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

    /**
     * Check if User Has Reviewed Stores
     *
     * @param Request $request
     * @return JsonResponse
     *
     * @group Stores
     * @bodyParam store_ids string required Comma separated store IDs to check. Example: 1,2,3
     * @response scenario=success {
     *     "data": {
     *         "1": {
     *             "store_id": 1,
     *             "store_name": "Store Name",
     *             "has_reviewed": true,
     *             "last_reviewed_at": "2025-01-16 11:40:23"
     *         }
     *     }
     * }
     */
    public function getCheckUserReviewedStore(Request $request)
    {
        $request->validate([
            'store_ids' => 'required|string'
        ]);

        $storeIds = explode(',', $request->store_ids);
        
        // get stores with their latest reviews by the authenticated user
        $stores = Store::whereIn('id', $storeIds)
            ->with(['storeRatings' => function($query) {
                $query->where('user_id', auth()->id())
                    ->latest();
            }])
            ->get();
        
        // create response array with store details and review status
        $result = $stores->mapWithKeys(function ($store) {
            $latestRating = $store->storeRatings->first();
            
            return [$store->id => [
                'store_id' => $store->id,
                'store_name' => $store->name,
                'has_reviewed' => !is_null($latestRating),
                'last_reviewed_at' => $latestRating ? $latestRating->created_at : null
            ]];
        })->toArray();

        return response()->json([
            'data' => $result
        ]);
    }

    /**
     * Get Stores by Merchant Offer Claim ID
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     *
     * @group Stores
     * @urlParam claim_id integer required The merchant offer claim ID. Example: 1
     * 
     * @response scenario=success {
     *   "data": {
     *     "id": 1,
     *     "name": "Store Name",
     *     ...
     *   }
     * }
     * @response scenario=error {
     *   "message": "Claim not found"
     * }
     */
    public function getStoreByClaim(Request $request)
    {
        $request->validate([
            'claim_id' => 'required|integer'
        ]);

        // find the claim
        $claim = MerchantOfferClaim::with('merchantOffer')->findOrFail($request->claim_id);
        
        if (!$claim || !$claim->merchantOffer) {
            return response()->json(['message' => 'Claim or merchant offer not found'], 404);
        }
        
        // get the merchant offer associated with the claim
        $merchantOffer = $claim->merchantOffer;
        
        // get all stores associated with the merchant offer
        $stores = $merchantOffer->stores()
            ->with([
                'merchant',
                'merchant.media',
                'storeRatings',
                'location',
                'categories',
                'parentCategories',
                'media'
            ])
            ->withCount([
                'storeRatings as store_ratings_count',
                'availableMerchantOffers'
            ])
            ->get();
            
        if ($stores->isEmpty()) {
            return response()->json(['message' => 'No stores found for this claim'], 404);
        }
        
        return StoreResource::collection($stores);
    }
}
