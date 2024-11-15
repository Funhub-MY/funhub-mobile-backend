<?php

namespace App\Http\Controllers\Api;

use App\Console\Commands\UpdateArticleTagsArticlesCount;
use App\Events\ArticleCreated;
use App\Events\RatedLocation;
use App\Http\Controllers\Controller;
use App\Http\Requests\ArticleCreateRequest;
use App\Http\Requests\ArticleImagesUploadRequest;
use App\Http\Requests\UpdateArticleRequest;
use App\Http\Resources\ArticleResource;
use App\Http\Resources\MerchantOfferResource;
use App\Http\Resources\PublicArticleResource;
use App\Http\Resources\UserResource;
use App\Models\Article;
use App\Models\ArticleCategory;
use App\Models\ArticleTag;
use App\Models\Country;
use App\Models\Interaction;
use App\Models\Location;
use App\Models\MerchantOffer;
use App\Models\State;
use App\Models\User;
use App\Models\View;
use App\Notifications\TaggedUserInArticle;
use App\Services\ArticleRecommenderService;
use App\Traits\QueryBuilderTrait;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use App\Jobs\BuildRecommendationsForUser;
use App\Jobs\ByteplusVODProcess;
use App\Jobs\UpdateArticleTagArticlesCount;
use App\Models\City;
use App\Models\SearchKeyword;
use App\Models\Setting;
use App\Models\ShareableLink;
use App\Models\Store;
use App\Models\UserBlock;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

use function PHPSTORM_META\map;

class ArticleController extends Controller
{
    use QueryBuilderTrait;

    /**
     * Get Articles for Logged in user (for Home Page)
     * Note: user's own posts will not show up on home page
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     *
     * @group Article
     *
     * @bodyParam article_ids array optional Article Ids to Filter. Example [1,2,3]
     * @bodyParam category_ids array optional Category Ids to Filter. Example: [1, 2, 3]
     * @bodyParam video_only integer optional Filter by Videos. Example: 1 or 0
     * @bodyParam following_only integer optional Filter by Articles by Users who logged in user is following. Example: 1 or 0
     * @bodyParam tag_ids array optional Tag Ids to Filter. Example: [1, 2, 3]
     * @bodyParam city string optional Filter by City. Example: Subang Jaya
     * @bodyParam lat float optional Filter by Lat of User (must provide lng). Example: 3.123456
     * @bodyParam lng float optional Filter by Lng of User (must provide lat). Example: 101.123456
     * @bodyParam radius integer optional Filter by Radius (in meters) if provided lat, lng. Example: 10000
     * @bodyParam location_id integer optional Filter by Location Id. Example: 1
     * @bodyParam store_id integer optional Filter by Store Id. Example: 1
     * @bodyParam include_own_article integer optional Include own article. Example: 1 or 0
     * @bodyParam disable_home_conditions boolean optional Disable Home Conditions like hidden from home or whitelisted. Example: 1 or 0
     * @bodyParam pinned_only integer optional Filter by Pinned Articles. Example: 1 or 0
     * @bodyParam build_recommendations boolean optional Build Recommendations On or Off, On by Default. Example: 1 or 0
     * @bodyParam refresh_recommendations boolean optional Refresh Recommendations. Example: 1 or 0
     * @bodyParam limit integer optional Per Page Limit Override. Example: 10
     * @response scenario=success {
     *  "data": [],
     *  "links": {},
     *  "meta": {
     *     "current_page": 1,
     *   }
     * }
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        $query = Article::published();

        if (!$request->has('include_own_article') || $request->include_own_article == 0) {
            $query->where('user_id', '!=', auth()->user()->id);
        }

        // video only
        if ($request->has('video_only') && $request->video_only == 1) {
            $query->where('type', 'video');
        }

        if ($request->has('category_ids')) {
            $query->whereHas('categories', fn ($q) => $q->whereIn('article_categories.id', explode(',', $request->category_ids)));
        }

        // Handle build_recommendations alongside article_ids
        if ($request->has('build_recommendations') && $request->build_recommendations == 1 && auth()->user()->has_article_personalization) {
            $recommendedArticleIds = auth()->user()->articleRecommendations()
                ->whereNull('last_viewed_at')
                ->inRandomOrder()
                ->pluck('article_id')
                ->toArray();

            if (!empty($recommendedArticleIds)) {
                // If there are existing article_ids, intersect with recommendations
                if ($request->has('article_ids')) {
                    $requestedIds = explode(',', $request->article_ids);
                    $intersectedIds = array_intersect($recommendedArticleIds, $requestedIds);

                    // If intersection exists, use it; otherwise fall back to requested IDs
                    $finalIds = !empty($intersectedIds) ? $intersectedIds : $requestedIds;
                    $query->whereIn('id', $finalIds);
                } else {
                    // No article_ids specified, just use recommendations
                    $query->whereIn('id', $recommendedArticleIds);
                }

                // Maintain the random order of recommendations
                $orderString = 'FIELD(id,' . implode(',', $recommendedArticleIds) . ')';
                $query->orderByRaw($orderString);
            } elseif ($request->has('article_ids')) {
                // If no recommendations but article_ids exist, use those
                $query->whereIn('id', explode(',', $request->article_ids));
            }
        } elseif ($request->has('article_ids')) {
            // Normal article_ids handling if no recommendations
            $query->whereIn('id', explode(',', $request->article_ids));
        }

        if ($request->has('tag_ids')) {
            $tagIds = explode(',', $request->tag_ids);
            $articlesTags = ArticleTag::whereIn('id', $tagIds)->get();
            $articlesTagsNames = $articlesTags->pluck('name')->unique()->toArray();

            $articlesTags = ArticleTag::whereIn('name', $articlesTagsNames)
                ->with('articles')
                ->get();

            $articleIds = $articlesTags->pluck('articles')->flatten()->pluck('id')->toArray();
            $query->whereIn('id', $articleIds);
        }

        if ($request->has('following_only') && $request->following_only == 1) {
            $myFollowings = auth()->user()->followings;

            $query->whereHas('user', function ($query) use ($myFollowings) {
                $query->whereIn('users.id', $myFollowings->pluck('id')->toArray());
            });
        }

        if (!$request->has('following_only')) {
            $query->where('visibility', 'public');
        }

        if ($request->has('city')) {
            $query->whereHas('location', function ($query) use ($request) {
                $query->where('city', 'like', '%' . $request->city . '%');
            });
        }

        if ($request->has('city_id')) {
            $query->whereHas('location', function ($query) use ($request) {
                $query->where('city_id', $request->city_id);
            });
        }

        if ($request->has('location_id')) {
            $query->whereHas('location', function ($query) use ($request) {
                $query->where('locations.id', $request->location_id);
            });
        }

        if ($request->has('store_id')) {
            $locationIds = DB::table('locatables as article_locatables')
                ->join('locatables as store_locatables', function ($join) use ($request) {
                    $join->on('article_locatables.location_id', '=', 'store_locatables.location_id')
                        ->where('store_locatables.locatable_type', Store::class)
                        ->where('store_locatables.locatable_id', $request->store_id);
                })
                ->where('article_locatables.locatable_type', Article::class)
                ->pluck('article_locatables.locatable_id');

            $query->whereIn('articles.id', $locationIds)
                ->public();
        }

        $this->filterArticlesBlockedOrHidden($query);

        if (!$request->has('following_only')
            && !$request->has('disable_home_conditions')
            && !$request->has('tag_ids')
            && !$request->has('location_id')) {
            $query->where(function ($query) {
                $query
                ->has('user.articleFeedWhitelist')
                ->orWhere('hidden_from_home', false);
            });
        }

        if ($request->has('pinned_only') && $request->pinned_only == 1) {
            $query->where('pinned_recommended', true);
        } else {
            $query->where('pinned_recommended', false);
        }

        if (!$request->has('lat') && !$request->has('lng')) {
            // Only apply latest() if we're not using recommendations ordering
            if (!($request->has('build_recommendations') && $request->build_recommendations == 1)) {
                $query->latest();
            }
        }

        $paginatePerPage = $request->has('limit') ? $request->limit : config('app.paginate_per_page');

        $data = $query->with(
                'media', 'imports', 'media.videoJob',
                'categories', 'subCategories', 'tags',
                'user', 'user.media',
                'comments', 'interactions.user', 'interactions.user.media',
                'location','location.state', 'location.country', 'location.ratings')
            ->withCount('media', 'tags', 'views', 'userFollowings')
            ->withCount(['userFollowers' => function ($query) {
                $query->where('status', User::STATUS_ACTIVE);
            }])
            ->withCount(['comments' => function ($query) {
                $query->whereNull('parent_id')
                    ->whereHas('user' , function ($query) {
                        $query->where('status', User::STATUS_ACTIVE);
                    });
            }])
            ->with(['interactions' => function ($query) {
                $query->whereHas('user', function ($query) {
                    $query->where('status', User::STATUS_ACTIVE);
                });
            }])
            ->paginate($paginatePerPage);

        // Handle merchant offers
        $locationIds = $data->pluck('location.0.id')->filter()->unique()->toArray();

        $storesWithOffers = DB::table('locatables as store_locatables')
            ->whereIn('store_locatables.location_id', $locationIds)
            ->where('store_locatables.locatable_type', Store::class)
            ->join('merchant_offer_stores', 'store_locatables.locatable_id', '=', 'merchant_offer_stores.store_id')
            ->join('merchant_offers', function ($join) {
                $join->on('merchant_offer_stores.merchant_offer_id', '=', 'merchant_offers.id')
                    ->where('merchant_offers.status', '=', MerchantOffer::STATUS_PUBLISHED)
                    ->where('merchant_offers.available_at', '<=', now())
                    ->where('merchant_offers.available_until', '>=', now());
            })
            ->pluck('store_locatables.location_id')
            ->unique();

        $data->each(function ($article) use ($storesWithOffers) {
            if ($article->location->isNotEmpty()) {
                $articleLocationId = $article->location->first()->id;
                $article->has_merchant_offer = $storesWithOffers->contains($articleLocationId);
            } else {
                $article->has_merchant_offer = false;
            }
        });

        return ArticleResource::collection($data);
    }

    /**
     * Save Article Recommendations (from Algolia)
     *
     * @param Request $request
     * @return JsonResponse
     *
     * @group Article
     * @bodyParam article_ids array required Article Ids to Filter. Example [1,2,3]
     * @response status=200 {
     * "message": "Article recommendations updated",
     * "user_id": 1,
     * "total_articles": 3
     * }
     * @response status=401 scenario="Unauthenticated" {"message": "Unauthenticated."}
     */
    public function postSaveArticleRecommendation(Request $request)
    {
        $request->validate([
            'article_ids' => 'required',
        ]);

        try {
            $articleIds = explode(',', $request->article_ids);

            // clear existing list
            auth()->user()->articleRecommendations()->delete();

            foreach ($articleIds as $articleId) {
                // insert
                auth()->user()->articleRecommendations()->create([
                    'user_id' => auth()->id(),
                    'article_id' => $articleId,
                    'last_viewed_at' => null,
                ]);
            }

            return response()->json([
                'message' => 'Article recommendations updated',
                'user_id' => auth()->id(),
                'total_articles' => count($articleIds),
            ]);
        } catch (\Exception $e) {
            Log::error('[ArticleController] Article recommendations update failed: ' . $e->getMessage());
            return response()->json(['message' => 'Article recommendations update failed. Please try again later']);
        }
    }

    /**
     * Get Articles Nearby
     *
     * @param Request $request
     * @return void
     *
     * @group Article
     * @bodyParam article_ids array optional Article Ids to Filter. Example [1,2,3]
     * @bodyParam category_ids array optional Category Ids to Filter. Example: [1, 2, 3]
     * @bodyParam video_only integer optional Filter by Videos. Example: 1 or 0
     * @bodyParam tag_ids array optional Tag Ids to Filter. Example: [1, 2, 3]
     * @bodyParam city string optional Filter by City Name. Example: Subang Jaya
     * @bodyParam lat float required Filter by Lat of User (must provide lng). Example: 3.123456
     * @bodyParam lng float required Filter by Lng of User (must provide lat). Example: 101.123456
     * @bodyParam radius integer optional Filter by Radius (in meters) if provided lat, lng. Example: 10000
     * @bodyParam include_own_article integer optional Include own article. Example: 1 or 0
     * @bodyParam limit integer optional Per Page Limit Override. Example: 10
     *
     * @response scenario=success {
     * "data": [],
     * }
     */
    public function getArticlesNearby(Request $request)
    {
        if (!config('app.search_location_use_algolia')) {
            return ArticleResource::collection([]);
        }

        $radius = $request->has('radius') ? $request->radius : config('app.location_default_radius'); // 10km default
        $page = $request->has('page') ? intval($request->page) : 1;
        $limit = $request->has('limit') ? $request->limit : config('app.paginate_per_page');

        if ($request->has('city')) {
            // direct use city
            $query = Article::query();
            $query->whereHas('location', function ($query) use ($request) {
                $query->where('city', 'like', '%' . $request->city . '%');
            });

            // pass to query builder
            $data = $this->articleQueryBuilder($query, $request)
            ->paginate($request->has('limit') ? $request->limit : config('app.paginate_per_page'));
        } else {
            // cache this
            $searchResults = Cache::remember('article'. $request->lat.'-'.$request->lng, 10, function () use ($request, $radius) {
                return Article::search('')->with([
                    'aroundLatLng' => $request->lat . ',' . $request->lng,
                    'aroundRadius' => $radius * 1000,
                    'aroundPrecision' => 50,
                    'hitsPerPage' => 150,
                ])->keys();
            });

            // $searchResults = Article::search('')->with([
            //     'aroundLatLng' => $request->lat . ',' . $request->lng,
            //     'aroundRadius' => $radius * 1000,
            //     'aroundPrecision' => 50,
            //     'hitsPerPage' => 150,
            // ])->keys();

            // does actual article query
            $query = Article::whereIn('id', $searchResults->toArray())
                ->orderByRaw('FIELD(id, ' . implode(',', $searchResults->toArray()) . ')');

            $query = $this->articleQueryBuilder($query, $request);
            $data = $query->paginate($limit);
            // $data = Article::search('')->with([
            //     'aroundLatLng' => $request->lat . ',' . $request->lng,
            //     'aroundRadius' => 'all',
            //     'hitsPerPage' => $limit,
            //     'page' => $page - 1,
            // ])
            // ->query(function ($query) use ($request) {
            //     $query = $this->articleQueryBuilder($query, $request);
            // })->paginate($request->has('limit') ? $request->limit : config('app.paginate_per_page'));

            Log::info('[ArticleController] Algolia Search Nearby Articles', [
                'lat' => $request->lat,
                'lng' => $request->lng,
                'radius' => $radius,
                'hitPerPage' => $limit,
                'algoliaPage' => $page - 1,
                'ids' => $searchResults->toArray()
            ]);
        }

        if ($data) {
            // get all article location ids
            $locationIds = $data->pluck('location.0.id')->filter()->unique()->toArray();

            $storesWithOffers = DB::table('locatables as store_locatables')
                ->whereIn('store_locatables.location_id', $locationIds)
                ->where('store_locatables.locatable_type', Store::class)
                ->join('merchant_offer_stores', 'store_locatables.locatable_id', '=', 'merchant_offer_stores.store_id')
                ->join('merchant_offers', function ($join) {
                    $join->on('merchant_offer_stores.merchant_offer_id', '=', 'merchant_offers.id')
                        ->where('merchant_offers.status', '=', MerchantOffer::STATUS_PUBLISHED)
                        ->where('merchant_offers.available_at', '<=', now())
                        ->where('merchant_offers.available_until', '>=', now());
                })
                ->pluck('store_locatables.location_id')
                ->unique();

            $data->each(function ($article) use ($storesWithOffers) {
                if ($article->location->isNotEmpty()) {
                    $articleLocationId = $article->location->first()->id;
                    $article->has_merchant_offer = $storesWithOffers->contains($articleLocationId);
                } else {
                    $article->has_merchant_offer = false;
                }
            });
        }
        return ArticleResource::collection($data);
    }

    /**
     * Search Articles
     *
     * @param Request $request
     * @return JsonResponse
     *
     * @group Article
     *
     * @bodyParam suggestion string Suggested keyword. Example: KL Food
     * @response scenario=success {
     *  "data": [],
     *  "links": {},
     *  "meta": {
     *     "current_page": 1,
     *   }
     * }
     *
     */
    public function articlesSearch(Request $request)
    {
        if (!config('app.search_location_use_algolia')) {
            return ArticleResource::collection([]);
        }

        $this->validate($request, [
            'suggestion' => 'required'
        ]);

        $data = Article::search($request->suggestion)
        ->query(function ($query) use ($request) {
            $query = $this->articleQueryBuilder($query, $request);
        })->paginate($request->has('limit') ? $request->limit : config('app.paginate_per_page'));
        return ArticleResource::collection($data);
    }

    /**
     * Query builder reusable for other methods
     *
     * @param QueryBuilder $query
     * @param Request $request
     * @return QueryBuilder
     */
    public function articleQueryBuilder($query, $request, $hasUser = true) {
        $query->published();

        if ($hasUser && (!$request->has('include_own_article') || $request->include_own_article == 0)) {
            // default to exclude own article
            $query->where('user_id', '!=', auth()->user()->id);
            // else it will also include own article
        }

        // video only
        if ($request->has('video_only') && $request->video_only == 1) {
            $query->where('type', 'video');
        }

        if ($request->has('category_ids')) {
            $query->whereHas('categories', fn ($q) => $q->whereIn('article_categories.id', explode(',', $request->category_ids)));
        }

        if ($request->has('article_ids')) {
            $query->whereIn('id', explode(',', $request->article_ids));
        }

        if ($request->has('tag_ids')) {
            $tagIds = explode(',', $request->tag_ids);

            // get all articles ids associated with this tag
            $articlesTags = ArticleTag::whereIn('id', $tagIds)->with('articles')->get();
            // get all articles ids
            $articleIds = $articlesTags->pluck('articles')->flatten()->pluck('id')->toArray();
            $query->whereIn('id', $articleIds);
        }

        $query->with([
            'user' => function ($query) {
                $query->without('pointLedgers');
            },
            'user.media' => function ($query) {
                $query->lazy();
            },
            'categories',
            'subCategories',
            'media',
            'media.videoJob',
            'tags',
            'location',
            'interactions' => function ($query) {
                $query->where('user_id', auth()->id());
            },
            'interactions.user' => function ($query) {
                $query->without('pointLedgers');
            },
        ])
        ->withCount('interactions', 'views', 'imports')
        ->withCount(['comments' => function ($query) {
            $query->whereNull('parent_id')
            ->whereHas('user' , function ($query) {
                $query->where('status', User::STATUS_ACTIVE);
            });
        }]);
        return $query;
    }

    /**
     * Filter Articles Blocked Or Hidden by User
     *
     * @param QueryBuilder $query
     * @return void
     */
    protected function filterArticlesBlockedOrHidden(&$query): void
    {
        $excludedUserIds = [];
        // if i'm in someone's blocked list, i should not able to see that user's articles
        $usersBlocks = UserBlock::where('blockable_type', User::class)
            ->where('blockable_id', auth()->user()->id)
            ->get();

        if ($usersBlocks) {
            $articleOwnersThatBlockedMe = $usersBlocks->pluck('user_id')->toArray();
            $excludedUserIds = array_merge($excludedUserIds, $articleOwnersThatBlockedMe);
        }
        // vice-versa: article owners should not see their blocked list articles
        $myBlockedUserIds = auth()->user()->usersBlocked()->pluck('blockable_id')->toArray();
        if ($myBlockedUserIds) {
            $excludedUserIds = array_merge($excludedUserIds, $myBlockedUserIds);
        }
        $query->whereNotIn('user_id', $excludedUserIds);

    }

    /**
     * Get Tagged users of article
     *
     * @param Request $request
     * @return UserResource
     *
     * @group Article
     * @bodyParam article_id integer required Article Id. Example: 1
     * @response scenario=success {
     *  "data": [],
     *  "links": {},
     *  "meta": {
     *     "current_page": 1,
     *   }
     * }
     */
    public function getTaggedUsersOfArticle(Request $request)
    {
        $this->validate($request, [
            'article_id' => 'required',
        ]);

        // ensure user has access to this articles to load tagged users
        $query = Article::published()->where('id', $request->article_id)
            ->whereDoesntHave('hiddenUsers', function ($query) {
                $query->where('user_id', auth()->user()->id);
            });

        // ensure user is not blocked by auth()->user()
        $myBlockedUserIds = auth()->user()->usersBlocked()->pluck('blockable_id')->toArray();
        $peopleWhoBlockedMeIds = auth()->user()->blockedBy()->pluck('user_id')->toArray();

        $article = $query->first();

        if (!$article) {
            return response()->json([
                'message' => 'Article not found'
            ], 404);
        }

        $taggedUsers = $article->taggedUsers()
            // where not suspended
            ->where('status', User::STATUS_ACTIVE)
            ->whereNotIn('users.id', array_unique(array_merge($myBlockedUserIds, $peopleWhoBlockedMeIds)))
            ->paginate(config('app.paginate_per_page'));

        return UserResource::collection($taggedUsers);
    }

    /**
     * Get Articles by User ID or Logged In User
     *
     * @param Request $request
     * @return \Illuminate\Http\Response
     *
     * @group Article
     * @group Article
     * @bodyParam user_id integer optional Load Spciefic User Articles. Example: 1
     * @bodyParam published_only boolean optional Filter by published articles. Example: true
     * @bodyParam filter string Column to Filter. Example: Filterable columns are: id, title, type, slug, status, published_at, created_at, updated_at
     * @bodyParam filter_value string Value to Filter. Example: Filterable values are: 1, 2, 3, 4, 5, 6, 7, 8, 9, 10
     * @bodyParam sort string Column to Sort. Example: Sortable columns are: id, title, type, slug, status, published_at, created_at, updated_at
     * @bodyParam order string Direction to Sort. Example: Sortable directions are: asc, desc
     * @bodyParam limit integer Per Page Limit Override. Example: 10
     * @bodyParam offset integer Offset Override. Example: 0
     * @bodyParam video_only integer optional Filter by Videos. Example: 1 or 0
     *
     * @response scenario=success {
     *  "data": [],
     *  "links": {},
     *  "meta": {
     *     "current_page": 1,
     *   }
     * }
     *
     */
    public function getMyArticles(Request $request)
    {
        $user_id = auth()->user()->id;
        $user = auth()->user();

        if ($request->has('user_id')) { // check if 'user_id' is present in the request
            $user = User::find($request->user_id); // override
            if ($user) {
                $user_id = $user->id;
                // check if this user blocked authenticated user
                if ($user && $user->usersBlocked->contains(auth()->user()->id)) {
                    return response()->json([
                        'message' => 'You are not allowed to view this user articles'
                    ], 403);
                }
            }
        }

        if ($user->profile_is_private && $user->id !== auth()->id()) {
            // check if this user followers has authenticated user
            if (!auth()->user()->followings()->where('following_id', $user_id)->exists()) {
                return response()->json([
                    'message' => 'User profile is private'
                ], 404);
            }
        }

        $query = Article::where('user_id', $user_id);
        // video only
        if ($request->has('video_only') && $request->video_only == 1) {
            $query->where('type', 'video');
        }

        if ($request->has('published_only')) {
            $query->where('status', Article::STATUS[1]);
        }

        $this->buildQuery($query, $request);

        $this->filterArticlesBlockedOrHidden($query);

        $data = $query->with('user', 'user.media', 'user.followers', 'comments', 'interactions', 'interactions.user', 'media', 'media.videoJob', 'categories', 'subCategories', 'tags', 'location', 'imports', 'location.state', 'location.country', 'location.ratings')
            ->withCount('interactions', 'media', 'categories', 'tags', 'views', 'imports', 'userFollowings')
            ->withCount(['userFollowers' => function ($query) {
                $query->where('status', User::STATUS_ACTIVE);
            }])
            ->withCount(['comments' => function ($query) {
                $query->whereNull('parent_id')
                ->whereHas('user' , function ($query) {
                    $query->where('status', User::STATUS_ACTIVE);
                });
            }])
            ->paginate(config('app.paginate_per_page'));

          // get all article location ids, this part of code used for getting merchant offer banenr in article
        $locationIds = $data->pluck('location.0.id')->filter()->unique()->toArray();

        $storesWithOffers = DB::table('locatables as store_locatables')
            ->whereIn('store_locatables.location_id', $locationIds)
            ->where('store_locatables.locatable_type', Store::class)
            ->join('merchant_offer_stores', 'store_locatables.locatable_id', '=', 'merchant_offer_stores.store_id')
            ->join('merchant_offers', function ($join) {
                $join->on('merchant_offer_stores.merchant_offer_id', '=', 'merchant_offers.id')
                    ->where('merchant_offers.status', '=', MerchantOffer::STATUS_PUBLISHED)
                    ->where('merchant_offers.available_at', '<=', now())
                    ->where('merchant_offers.available_until', '>=', now());
            })
            ->pluck('store_locatables.location_id')
            ->unique();

        $data->each(function ($article) use ($storesWithOffers) {
            if ($article->location->isNotEmpty()) {
                $articleLocationId = $article->location->first()->id;
                $article->has_merchant_offer = $storesWithOffers->contains($articleLocationId);
            } else {
                $article->has_merchant_offer = false;
            }
        });

        return ArticleResource::collection($data);
    }

    /**
     * Get Bookmarked Articles by User ID or Logged In User
     *
     * @param Request $request
     * @return \Illuminate\Http\Response
     *
     * @group Article
     * @bodyParam user_id integer optional Load Spciefic User Bookmarked Articles. Example: 1
     * @bodyParam filter string Column to Filter. Example: Filterable columns are: id, title, type, slug, status, published_at, created_at, updated_at
     * @bodyParam filter_value string Value to Filter. Example: Filterable values are: 1, 2, 3, 4, 5, 6, 7, 8, 9, 10
     * @bodyParam sort string Column to Sort. Example: Sortable columns are: id, title, type, slug, status, published_at, created_at, updated_at
     * @bodyParam order string Direction to Sort. Example: Sortable directions are: asc, desc
     * @bodyParam limit integer Per Page Limit Override. Example: 10
     * @bodyParam offset integer Offset Override. Example: 0
     * @bodyParam video_only integer optional Filter by Videos. Example: 1 or 0
     *
     * @response scenario=success {
     *  "data": [],
     *  "links": {},
     *  "meta": {
     *     "current_page": 1,
     *   }
     * }
     *
     */
    public function getMyBookmarkedArticles(Request $request)
    {
        $user_id = auth()->user()->id;
        if ($request->has('user_id')) { // check if 'user_id' is present in the request
            // check if this user blocked authenticated user
            $user = User::find($request->user_id);
            if ($user) {
                $user_id = $user->id;
                if ($user && $user->usersBlocked->contains(auth()->user()->id)) {
                    return response()->json([
                        'message' => 'You are not allowed to view this user articles'
                    ], 403);
                }
            }
        }

        $query = Article::whereHas('interactions', function ($query) use ($user_id) {
            $query->where('user_id', $user_id)
                ->where('type', Interaction::TYPE_BOOKMARK);
        })->published()
            ->whereDoesntHave('hiddenUsers', function ($query) use ($user_id) {
                $query->where('user_id', $user_id);
            });

        $this->buildQuery($query, $request);

        // video only
        if ($request->has('video_only') && $request->video_only == 1) {
            $query->where('type', 'video');
        }

        $data = $query->with('user', 'user.media', 'user.followers', 'comments', 'interactions', 'interactions.user', 'media', 'media.videoJob', 'categories', 'subCategories', 'tags', 'location', 'imports', 'location.state', 'location.country', 'location.ratings')
            ->withCount('interactions', 'media', 'categories', 'tags', 'views', 'imports', 'userFollowings')
            ->withCount(['userFollowers' => function ($query) {
                $query->where('status', User::STATUS_ACTIVE);
            }])
            ->withCount(['comments' => function ($query) {
                $query->whereNull('parent_id')
                ->whereHas('user' , function ($query) {
                    $query->where('status', User::STATUS_ACTIVE);
                });
            }])
            ->paginate(config('app.paginate_per_page'));

        return ArticleResource::collection($data);
    }

    /**
     * Hide Article When Not Interested By user
     *
     * @param Request $request
     * @return void
     *
     * @group Article
     * @bodyParam article_id integer required Article Id. Example: 1
     * @response scenario=success {
     * "message": "Article marked as not interested"
     * }
     */
    public function postNotInterestedArticle(Request $request)
    {
        $this->validate($request, [
            'article_id' => 'required',
        ]);

        $article = Article::find($request->article_id);
        if (!$article) {
            return response()->json([
                'message' => 'Article not found'
            ], 404);
        }

        $article->hiddenUsers()->syncWithoutDetaching(auth()->user()->id);

        // remove from article ranks of user
        auth()->user()->articleRanks()->where('article_id', $article->id)->delete();

        return response()->json([
            'message' => 'Article marked as not interested'
        ]);
    }

    /**
     * Create New Article
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     *
     * @group Article
     * @bodyParam title string required The title of the article. Example: This is a title
     * @bodyParam type string required The type of the article. Example: multimedia,text,video
     * @bodyParam body string required The body of the article. Example: This is a caption or body of article
     * @bodyParam status integer required The status of the article. Example: 0 is Draft,1 is Published
     * @bodyParam published_at datetime The published date of the article. Example: 2021-02-21 12:00:00
     * @bodyParam tags array The tags of the article. Example: ["#tag1", "#tag2"]
     * @bodyParam categories array The categories ID of the article. Example: [1, 2]
     * @bodyParam images array The images IDs. Must first call upload images endpoint. Example: [1, 2]
     * @bodyParam video integer The video ID. Must first call upload videos endpoint. Example: 1
     * @bodyParam excerpt string The excerpt of the article. Example: This is a excerpt of article
     * @bodyParam location string The location of the article. Example: {"lat": 123, "lng": 123, "name": "location name", "address": "location address", "address_2" : "", "city": "city", "state": "state name/id", "postcode": "010000", "rating": "5"}
     * @bodyParam tagged_user_ids array The tagged users IDs. Example: [1, 2]
     * @bodyParam visibility string The visibility of the article. Example: public,private
     *
     * @response scenario=success {
     * "message": "Article updated",
     * }
     * @response status=422 scenario="Invalid Form Fields" {"errors": ["body": ["The Body field is required."] ]}
     * @response status=404 scenario="Not Found" {"message": "Comment not found"}
     */
    public function store(ArticleCreateRequest $request)
    {
        $user = auth()->user();

        // slug
        $slug = strtolower(Str::random(12));

        $taggedUsers = null;
        // tag users in article
        if ($request->has('tagged_user_ids') && $request->tagged_user_ids !== 'null') {
            // check if user's followers are in tagged user ids list
            $followers = $user->followers()->whereIn('users.id', $request->tagged_user_ids)->get();
            if ($followers->count() != count($request->tagged_user_ids)) {
                return response()->json([
                    'message' => 'You can only tag your followers.',
                ], 422);
            }

            $taggedUsers = User::whereIn('id', $request->tagged_user_ids)->get();
        }

        // get user profile settings from privacy
        $privateProfile = $user->profile_is_private;

        // by default if visibility not set and user is private profile, all articles created will set to private first
        if ($privateProfile && !$request->has('visibility')) {
            $request->merge(['visibility' => 'private']);
        }

        // settings "new_article_hide_from_home" is true, then set hide_from_home to true
        $hideFromHome = Setting::where('key', 'new_article_hide_from_home')->first();
        if ($hideFromHome && ($hideFromHome->value == 1 || $hideFromHome->value == 'true')) {
            $request->merge(['hidden_from_home' => true]);
        }

        $article = Article::create([
            'title' => $request->title,
            'type' => $request->type,
            'slug' => $slug,
            'source' => 'mobile',
            'excerpt' => ($request->excerpt) ?? null,
            'body' => $request->body,
            'status' => $request->status, // default is draft
            'published_at' => ($request->published_at) ? Carbon::parse($request->published_at)->toDateTimeString() : null,
            'user_id' => $user->id,
            'visibility' => $request->visibility ?? 'public',
            'hidden_from_home' => $request->hidden_from_home ?? false,
        ]);


        // if request->images attach images from user_uploads to article_images collection media library
        if ($request->has('images')) {
            $userUploads = $user->getMedia(User::USER_UPLOADS)->whereIn('id', $request->images);
            $userUploads->each(function ($media) use ($article) {
                // move to article_gallery collection of the created article
                $media->move($article, Article::MEDIA_COLLECTION_NAME);
            });
        }

        // if request->video attach video from user_videos to article_videos collection media library
        if ($request->has('video')) {
            $userVideos = $user->getMedia(User::USER_VIDEO_UPLOADS)->whereIn('id', $request->video);
            if (!$userVideos || count($userVideos) <= 0) { // TODO: in future completely remove line above, as all uploads will be synced to USER_VIDEO_UPLOADS
                // get from user uplaods
                $userVideos = $user->getMedia(User::USER_UPLOADS)->whereIn('id', $request->video);
            }
            $userVideos->each(function ($media) use ($article) {
                // move to article_videos collection of the created article
                $movedMediaItem = $media->move($article, Article::MEDIA_COLLECTION_NAME);

                // when move to article_videos, dispatch byteplus video process
                if (str_contains($movedMediaItem->mime_type, 'video')) {
                    // get latest $media after move
                    ByteplusVODProcess::dispatch($movedMediaItem);
                }
            });
        }

        // attach categories, do not create new categories if not exist
        if ($request->has('categories')) {
            // categories must be array of ids of article categories
            $article->categories()->attach($request->categories);
        }

        // attach tags, if doesn't exist create new with # as prefix
        if ($request->has('tags')) {
            $tags = $request->tags;
            $tags = array_map(function ($tag) {
                return Str::startsWith($tag, '#') ? $tag : '#' . $tag;
            }, $tags);

            // create or update tags
            $tags = collect($tags)->map(function ($tag) {
                return ArticleTag::firstOrCreate(['name' => $tag, 'user_id' => auth()->id()])->id;
            });
            $article->tags()->attach($tags);
            $article->refresh();
            $article->tags->each(function ($tag) {
                UpdateArticleTagArticlesCount::dispatch($tag);
            });
        }

        // attach location with rating
        if ($request->has('location') && $request->location !== 'null') {
            try {
                $loc = $this->createOrAttachLocation($article, $request->location);
            } catch (\Exception $e) {
                Log::error('Location error', ['error' => $e->getMessage(), 'location' => $request->location]);
            }
        }

        // tag users in article
        if ($taggedUsers) {
            $article->taggedUsers()->attach($taggedUsers);

            // notifiy tagged user
            $taggedUsers->each(function ($taggedUser) use ($article) {
                try {
                    $locale = $taggedUser->last_lang ?? config('app.locale');
                    $taggedUser->notify((new TaggedUserInArticle($article, $article->user))->locale($locale));
                } catch (\Exception $e) {
                    Log::error('Notification error when tagged user', ['message' => $e->getMessage(), 'user' => $taggedUser]);
                }
            });
        }

        event(new ArticleCreated($article));

        // trigger scout to reindex this article
        $article->searchable();

        $article = $article->refresh();
        // load relations
        $article->load('user', 'comments', 'interactions', 'media', 'media.videoJob', 'categories', 'tags', 'location', 'views', 'location.ratings', 'taggedUsers');
        return response()->json([
            'message' => 'Article created',
            'article' => new ArticleResource($article),
        ]);
    }

    /**
     * Prep a Location Data
     *
     * @param Article $article
     * @param array $locationData
     * @return array
     */
    private function createOrAttachLocation($article, $locationData)
    {
        // search by google_id first if there is in locationData
        $location = null;
        if (isset($locationData['google_id']) && $locationData['google_id'] != 0) {
            $location = Location::where('google_id', $locationData['google_id'])->first();
        } else {
            // if location cant be found by google_id, then find by lat,lng
            $location = Location::where('lat', $locationData['lat'])
                ->where('lng', $locationData['lng'])
                ->first();
        }

        // detach existing location first
        $article->location()->detach(); // detaches all

        // Mall outlets incorrect attaching issue
        // if location exists, check if is_mall, if is mall check if name of locationData same as location name
        if ($location && $location->is_mall && $locationData['name'] != $location->name) {
            // eg. location name is Chagee @ Sunway Pyramid it will have same lat,lng and google_id as Sunway Pyramid
            // to prevent Chagee @ Sunway Pyramid being attached incorrectly to Sunway Pyramid
            // search again with name of locationData, lat, lng. instead of just google_id or lat/lng
            $location = Location::where('lat', $locationData['lat'])
                ->where('lng', $locationData['lng'])
                ->where('name', $locationData['name'])
                ->first();
        }

        if ($location) {
            // just attach to article with new ratings if there is
            $article->location()->attach($location->id);

            // update google id if there is
            if (isset($locationData['google_id']) && $locationData['google_id'] != 0) {
                $location->google_id = $locationData['google_id'];
                $location->save();
            }
        } else {
            // create new location
            $loc = [
                'name' => $locationData['name'],
                'google_id' => isset($locationData['google_id']) ? $locationData['google_id'] : null,
                'lat' => $locationData['lat'],
                'lng' => $locationData['lng'],
                'address' => $locationData['address'] ?? '',
                'address_2' => $locationData['address_2'] ?? '',
                'zip_code' => $locationData['postcode'] ?? '',
                'city' => $locationData['city'] ?? '',
            ];

            // find state by id if the locationdata state is integer else find by name
            $state = null;
            if (is_numeric($locationData['state'])) {
                $state = State::where('id', $locationData['state'])->first();
            } else {
                // where lower(name) like %trim lower locationData['state']%
                $state = State::whereRaw('lower(name) like ?', ['%' . trim(strtolower($locationData['state'])) . '%'])->first();
            }

            if ($state) {
                $loc['state_id'] = $state->id;

                // find country by state id
                $country = Country::where('id', $state->country_id)->first();
                $loc['country_id'] = $country->id;
            } else {
                // create new state and country
                // default to Malaysia
                // check if locationData has country
                $country = null;
                if (isset($locationData['country']) && $locationData['country'] != 0) {
                    $country = Country::where('name', 'like', '%' . $locationData['country'] . '%')->first();
                }

                if (!$country) {
                    // defaults to malaysia
                    $country = Country::where('name', 'Malaysia')->first();
                }

                // create state
                $state = State::create([
                    'name' => $locationData['state'],
                    'code' => 'CUSTOM' . ucwords(Str::random(3)),
                    'country_id' => $country->id,
                ]);
                Log::info('Created new state as state data not found', ['state' => $state]);

                $loc['state_id'] = $state->id;
                $loc['country_id'] = $country->id;
            }

            $location = $article->location()->create($loc);
        }

        // link it to location
        if (isset($locationData['city']) && $locationData['city'] != 0) {
            $city = City::where('name', 'like', '%' . $locationData['city'] . '%')->first();
            if ($city) {
                $location->city_id = $city->id;
                $location->save();
            }
        }

        if ($location && $locationData['rating'] &&  $locationData['rating'] != 0) {
            // if have ratings, update it, else create new
            $rating = $location->ratings()->where('user_id', auth()->id())->first();
            if ($rating) {
                $rating->rating = $locationData['rating'];
                $rating->save();
            } else {
                $location->ratings()->create([
                    'user_id' => auth()->id(),
                    'rating' => $locationData['rating'],
                ]);

                // fire event
                event(new RatedLocation($location, auth()->user(), $locationData['rating'], $article->id));
            }

            // only add to average ratings if article is public
            if ($article->visibility == Article::VISIBILITY_PUBLIC) {
                // recalculate average ratings
                $location->average_ratings = $location->ratings()->avg('rating');
                $location->save();
            }
        }
        return $location;
    }

    /**
     * Get One Article by ID
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     *
     * @group Article
     * @urlParam id integer required The id of the article. Example: 1
     * @response scenario=success {
     *  "article": {}
     * }
     * @response status=404 scenario="Not Found" {"message": "Article not found"}
     */
    public function show($id)
    {
        $article = Article::with('user', 'user.followers', 'comments', 'media', 'categories', 'tags', 'location', 'location.ratings', 'taggedUsers')
        ->withCount('media', 'categories', 'tags', 'views', 'imports', 'userFollowings')
        ->withCount(['userFollowers' => function ($query) {
            $query->where('status', User::STATUS_ACTIVE);
        }])
        // withCount comment where dont have parent_id
        ->withCount(['comments' => function ($query) {
            $query->whereNull('parent_id')
            ->whereHas('user' , function ($query) {
                $query->where('status', User::STATUS_ACTIVE);
            });
        }])
        // counter also must be active users only for any interactions
        ->withCount(['interactions' => function ($query) {
            // user must be active
            $query->whereHas('user', function ($query) {
                $query->where('status', User::STATUS_ACTIVE);
            });
        }])
        // interactions where user is active
        ->with(['interactions' => function ($query) {
            $query->whereHas('user', function ($query) {
                $query->where('status', User::STATUS_ACTIVE);
            });
        }])
        ->published()
            ->whereDoesntHave('hiddenUsers', function ($query) {
                $query->where('user_id', auth()->user()->id);
            })
            ->findOrFail($id);

         if ($article->location->isNotEmpty()) {
            // get all article location ids, this part of code used for getting merchant offer banenr in article
            $locationIds = $article->location->pluck('id')->toArray();

            $storesWithOffers = DB::table('locatables as store_locatables')
                ->whereIn('store_locatables.location_id', $locationIds)
                ->where('store_locatables.locatable_type', Store::class)
                ->join('merchant_offer_stores', 'store_locatables.locatable_id', '=', 'merchant_offer_stores.store_id')
                ->join('merchant_offers', function ($join) {
                    $join->on('merchant_offer_stores.merchant_offer_id', '=', 'merchant_offers.id')
                        ->where('merchant_offers.status', '=', MerchantOffer::STATUS_PUBLISHED)
                        ->where('merchant_offers.available_at', '<=', now())
                        ->where('merchant_offers.available_until', '>=', now());
                })
                ->pluck('store_locatables.location_id')
                ->unique();

            if ($article->location->isNotEmpty()) {
                $articleLocationId = $article->location->first()->id;
                $article->has_merchant_offer = $storesWithOffers->contains($articleLocationId);
            } else {
                $article->has_merchant_offer = false;
            }
        }

        return response()->json([
            'article' => new ArticleResource($article)
        ]);
    }

    /**
     * Update article by ID. (Only owner of article can update)
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     *
     * @group Article
     * @urlParam id integer required The id of the article. Example: 1
     * @bodyParam title string required The title of the article, will regenerate slug. Example: This is a title
     * @bodyParam body string required The body of the article. Example: This is a comment
     * @bodyParam status integer required The status of the article, change this to 0 to unpublish. Example: 0 is Draft,1 is Published
     * @bodyParam tags array The tags of the article. Example: ["#tag1", "#tag2"]
     * @bodyParam categories array The categories ID of the article. Example: [1, 2]
     * @bodyParam images file The images ID of the article.
     * @bodyParam video file The video ID of the article.
     * @bodyParam tagged_user_ids array The tagged user IDs of the article. Example: [1, 2]
     * @bodyParam location string The location of the article. Example: {"lat": 123, "lng": 123, "name": "location name", "address": "location address", "address_2" : "", "city": "city", "state": "state name/id", "postcode": "010000", "rating": "5"}
     * @bodyParam visibility string The visibility of the article. Example: public,private
     *
     * @response scenario=success {
     * "message": "Article updated",
     * }
     * @response status=422 scenario="Invalid Form Fields" {"errors": ["body": ["The Body field is required."] ]}
     * @response status=404 scenario="Not Found" {"message": "Comment not found"}
     */
    public function update(UpdateArticleRequest $request, $id)
    {
        // check if owner of Article
        $article = Article::where('id', $id)->where('user_id', auth()->user()->id)
            ->first();

        if ($article) {
            $user = auth()->user();
            $taggedUsers = null;
            // tag users in article
            if ($request->has('tagged_user_ids') && $request->tagged_user_ids !== 'null') {
                // check if user's followers are in tagged user ids list
                $followers = $user->followers()->whereIn('users.id', $request->tagged_user_ids)->get();
                if ($followers->count() != count($request->tagged_user_ids)) {
                    return response()->json([
                        'message' => 'You can only tag your followers.',
                    ], 422);
                }

                $taggedUsers = User::whereIn('id', $request->tagged_user_ids)->get();
            }

            // slug
            $slug = '';
            if ($request->has('title')) {
                $slug = strtolower(Str::random(12));
            }

            // updates
            $article->update([
                'title' => ($request->title) ? $request->title : $article->title,
                'slug' => ($slug) ? $slug : $article->slug,
                'body' => $request->body,
                'status' => $request->status,
            ]);

            // update visibility if there is
            if ($request->has('visibility')) {
                $article->visibility = $request->visibility;
                $article->save();
                Log::info('Visibility updated for article ID'. $article->id, ['visibility' => $request->visibility]);
            }

            // if request->images attach images from user_uploads to article_images collection media library
            if ($request->has('images')) {
                $userUploads = $user->getMedia(User::USER_UPLOADS)->whereIn('id', $request->images);
                $userUploads->each(function ($media) use ($article) {
                    // move to article_gallery collection of the created article
                    $media->move($article, Article::MEDIA_COLLECTION_NAME);
                });
            }

            // detach and delete images no longer in request->images
            if ($request->has('images') || $request->has('video')) {
                $article->getMedia(Article::MEDIA_COLLECTION_NAME)->each(function ($media) use ($request) {
                    // images gallery
                    if (!in_array($media->id, $request->images)) {
                        $media->delete();
                    }
                    // video
                    if (!in_array($media->id, $request->video)) {
                        $media->delete();
                    }
                });
            }

            // sync category
            if ($request->has('categories')) {
                // explode categories
                $category_ids = $request->categories;
                // check if category exists before sync
                $categories = ArticleCategory::whereIn('id', $category_ids)->pluck('id');
                $article->categories()->sync($categories);
            }

            // sync tags
            if ($request->has('tags')) {
                $tags = $request->tags;
                $tags = array_map(function ($tag) {
                    return Str::startsWith($tag, '#') ? $tag : '#' . $tag;
                }, $tags);

                // create or update tags
                $tags = collect($tags)->map(function ($tag) {
                    return ArticleTag::firstOrCreate(['name' => $tag, 'user_id' => auth()->id()])->id;
                });
                $article->tags()->sync($tags);
            }

            // tag users in article
            if ($taggedUsers) {
                $article->taggedUsers()->sync($taggedUsers);

                // notifiy tagged user
                $article->taggedUsers->each(function ($taggedUser) use ($article) {
                    try {
                        $taggedUser->notify(new TaggedUserInArticle($article, $article->user));
                    } catch (\Exception $e) {
                        Log::error('Notification error when tagged user', ['message' => $e->getMessage(), 'user' => $taggedUser]);
                    }
                });
            }

            // attach location with rating
            if ($request->has('location') && $request->location !== 'null') {
                try {
                    // delete article->location->ratings where user_id is current article->user_id
                    $location = $article->location->first();
                    if ($location && $location->has('ratings')) {
                        $location->ratings()->where('user_id', $article->user_id)->delete();
                    }

                    // create or attach new location with ratings
                    $loc = $this->createOrAttachLocation($article, $request->location); // this will detach existing location if changed
                } catch (\Exception $e) {
                    Log::error('Location error', ['error' => $e->getMessage(), 'location' => $request->location]);
                }
            }

            // refresh article with its relations
            $article = $article->refresh();
            // trigger scout to reindex this article
            $article->searchable();
            // load relations count
            $article->loadCount('comments', 'interactions', 'media', 'categories', 'tags', 'views', 'imports');
            return response()->json(['message' => 'Article updated', 'article' => new ArticleResource($article)]);
        } else {
            return response()->json(['message' => 'Article not found'], 404);
        }
    }

    /**
     * Remove article by ID. (Only owner of article can delete)
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     *
     * @group Article
     * @urlParam id integer required The id of the Article. Example: 1
     * @response scenario=success {
     * "message": "Article deleted"
     * }
     * @response status=404 scenario="Not Found" {"message": "Article not found"}
     */
    public function destroy($id)
    {
        // check if owner of Article
        $article = Article::where('id', $id)->where('user_id', auth()->id());
        if ($article->exists()) {
            $article = $article->first();
            // destroy ratings if article->location has ratings by this article owner
            if ($article->has('location')) {
                $loc = $article->location->first();
                // if artilce locaiton has ratings, get current article owner's ratings
                if ($loc && $loc->has('ratings')) {
                    $ratings = $loc->ratings()->where('user_id', $article->user_id)->get();
                    // delete ratings
                    if ($ratings->count()) {
                        $ratings->each(function ($rating) {
                            $rating->delete();
                        });
                        // update average ratings for location
                        $loc->update([
                            'average_ratings' => $loc->ratings()->avg('rating')
                        ]);
                    }
                }
            }

            // unattach all tagged users
            $article->taggedUsers()->detach();

            // unattach location from article
            $article->location()->detach();

            // delete article related media to save space
            $article->getMedia(Article::MEDIA_COLLECTION_NAME)->each(function ($media) {
                $media->delete();
            });

            // delete article
            $article->delete();
            return response()->json(['message' => 'Article deleted']);
        } else {
            return response()->json(['message' => 'Article not found'], 404);
        }
    }

    /**
     * Upload Images for Article
     *
     * @param ArticleImagesUploadRequest $request
     * @return \Illuminate\Http\JsonResponse
     *
     * @group Article
     * @bodyParam images file required The images to upload.
     * @bodyParam is_cover boolean used to set this image as a cover. Example:1
     * @response scenario=success {
     * "uploaded": [
     *     {
     *        "id": 1,
     *        "name": "image.jpg",
     *        "url": "http://localhost:8000/storage/user_uploads/1/image.jpg",
     *        "size": 12345,
     *        "type": "image/jpeg"
     *    }
     * ]
     * }
     * @response status=422 scenario="Invalid Form Fields" {"errors": ["images": ["The images field is required."] ]}
     */
    public function postGalleryUpload(ArticleImagesUploadRequest $request)
    {
        $user = auth()->user();
        $images = [];
        // if request images is not array wrap it in array
        if (!is_array($request->images)) {
            // upload via spatie medialibrary
            // single image

            $uploaded = $user->addMedia($request->images)
                ->withCustomProperties(['is_cover' => $request->is_cover])
                ->toMediaCollection(
                    'user_uploads',
                    (config('filesystems.default') == 's3' ? 's3_public' : config('filesystems.default')),
                );
            return response()->json([
                'uploaded' => [
                    [
                        'id' => $uploaded->id,
                        'name' => $uploaded->file_name,
                        'url' => $uploaded->getUrl(),
                        'size' => $uploaded->size,
                        'type' => $uploaded->mime_type,
                    ],
                ],
            ]);
        } else {
            // multiple images
            $uploaded = collect($request->images)->map(function ($image) use ($user) {
                return $user->addMedia($image)
                    ->withCustomProperties(['is_cover' => $request->is_cover])
                    ->toMediaCollection(
                        'user_uploads',
                        (config('filesystems.default') == 's3' ? 's3_public' : config('filesystems.default')),
                    );
            });
            $uploaded->each(function ($image) use (&$images) {
                $images[] = [
                    'id' => $image->id,
                    'name' => $image->file_name,
                    'url' => $image->getUrl(),
                    'size' => $image->size,
                    'type' => $image->mime_type,
                ];
            });
            return response()->json([
                'uploaded' => $images,
            ]);
        }
    }

    /**
     * Upload Video for Article
     *
     * Video size must not larger than 500MB, will stream video response back to client on progress via header X-Upload-Progress / calculate your own using X-Content-Duration
     *
     * Must be able to stream completion percentage back to client
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     *
     * @group Article
     * @bodyParam video file required The video to upload.
     * @response scenario=success {
     * "url" : "http://localhost:8000/storage/user_video_uploads/1/video.mp4"
     * }
     */
    public function postVideoUpload(Request $request)
    {
        // validate video size must not larger than 500MB
        $request->validate([
            'video' => 'required|file|max:' . config('app.max_size_per_video_kb'),
        ]);
        $videoFile = $request->file('video');
        $user = auth()->user();

        // Create new media item in the "user_uploads" collection
        $media = $user->addMedia($videoFile)
            ->toMediaCollection(
                User::USER_VIDEO_UPLOADS,
                (config('filesystems.default') == 's3' ? 's3_public' : config('filesystems.default'))
            );

        $filesystem = config('filesystems.default');

        if ($filesystem == 's3' || $filesystem == 's3_public') {
            $fullUrl = Storage::disk(config('filesystems.default'))->url($media->getPath());
            $stream = Storage::disk(config('filesystems.default'))->readStream($media->getPath());
            $filesize = Storage::disk(config('filesystems.default'))->size($media->getPath());
        } else {
            $fullUrl = Storage::url($media->getPath());
            $stream = Storage::readStream($media->getPath());
            $filesize = Storage::size($media->getPath());
        }

        $chunksize = 1024 * 1024; // 1MB chunks
        $bytesRead = 0;

        while (!feof($stream)) {
            $chunk = fread($stream, $chunksize);
            $bytesRead += strlen($chunk);
            $progress = min(100, round($bytesRead / $filesize * 100));

            $response = response($chunk, 200, [
                'Content-Type' => $videoFile->getClientMimeType(),
                'X-Upload-Progress' => $progress,
                'X-Content-Duration' => $filesize,
                'X-Content-Url' => $fullUrl,
                'Media-Id' => $media->id,
            ]);

            ob_end_clean();
            $response->send();
            flush();
        }

        fclose($stream);
        return response()->json(['url' => $fullUrl]);
    }

    /**
     * Report an article
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     *
     * @group Article
     * @subgroup Reports
     * @bodyParam article integer required The id of the article. Example: 1
     * @bodyParam reason string required The reason for reporting the comment. Example: Spam     * @bodyParam violation_type required The violation type of this report
     * @bodyParam violation_level required The violation level of this report
     * @response scenario=success {
     * "message": "Comment reported",
     * }
     * @response status=422 scenario="Invalid Form Fields" {"errors": ["article_id": ["The Article Id field is required."] ]}
     * @response status=422 scenario="Invalid Form Fields" {"message": "You have already reported this comment" ]}
     */
    public function postReportArticle(Request $request)
    {
        // validate
        $request->validate([
            'article_id' => 'required|integer',
            'reason' => 'required|string',
            'violation_type' => 'required|string',
            'violation_level' => 'required|integer',
        ]);
        // find article
        $article = Article::where('id', request('article_id'))->firstOrFail();
        // check if user has reported this comment before if not create
        if (!$article->reports()->where('user_id', auth()->id())->exists()) {
            $article->reports()->create([
                'user_id' => auth()->id(),
                'reason' => request('reason'),
                'violation_type' => request('violation_type'),
                'violation_level' => request('violation_level'),
            ]);
        } else {
            return response()->json(['message' => 'You have already reported this comment'], 422);
        }
        return response()->json(['message' => 'Comment reported']);
    }

    /**
     * Get Article Cities (Unique)
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     *
     * @group Article
     * @urlParam search string optional Search for city. Example: "Kota"
     * @response scenario=success {
     * "cities": []
     * }
     */
    public function getArticleCities(Request $request)
    {
        // get all unique article->locations
        $query = Location::select('city')
            ->orderBy('city', 'asc')
            ->distinct();

        if ($request->has('search')) {
            // search by like
            $query->where('city', 'like', '%' . $request->search . '%');
        }

        $locationWithCity = $query->get();

        // get all unique cities into an array
        $cities = $locationWithCity->pluck('city')->toArray();

        return response()->json([
            'cities' => $cities,
        ]);
    }

    /**
     * Get Article Merchant Offers
     *
     * @param Request $request
     * @param Article $article
     * @return MerchantOfferResource
     *
     * @group Article
     * @urlParam article integer required The id of the article. Example: 1
     *
     * @response scenario=success {
     * "data": [
     * {}
     * ]
     * }
     */
    public function getArticleMerchantOffers(Article $article)
    {
        // Get all article location IDs
        $locationIds = $article->location->pluck('id')->toArray();

        // Get store IDs with available merchant offers matching the article locations
        $storeIdsWithOffers = DB::table('locatables as store_locatables')
            ->whereIn('store_locatables.location_id', $locationIds)
            ->where('store_locatables.locatable_type', Store::class)
            ->join('merchant_offer_stores', 'store_locatables.locatable_id', '=', 'merchant_offer_stores.store_id')
            ->join('merchant_offers', function ($join) {
                $join->on('merchant_offer_stores.merchant_offer_id', '=', 'merchant_offers.id')
                    ->where('merchant_offers.status', '=', MerchantOffer::STATUS_PUBLISHED)
                    ->where('merchant_offers.available_at', '<=', now())
                    ->where('merchant_offers.available_until', '>=', now());
            })
            ->pluck('merchant_offer_stores.merchant_offer_id')
            ->unique();

        // Retrieve the merchant offers associated with the store IDs
        $merchantOffers = MerchantOffer::whereIn('id', $storeIdsWithOffers)
            ->with('merchant')
            ->published()
            ->available()
            ->paginate(config('app.paginate_per_page'));

            return MerchantOfferResource::collection($merchantOffers);
    }

    /**
     * Get Article (public)
     *
     * @param Article $article
     * @return void
     */
    public function getArticleForPublicView(Request $request)
    {
        $this->validate($request, [
            'share_code' => 'required|string'
        ]);

        // get article by ShareableLink
        $share = ShareableLink::where('link', $request->share_code)
            ->where('model_type', Article::class)
            ->first();

        if (!$share) {
            return abort(404);
        }
        $article = $share->model;

        // if article is published only return
        if ($article->status != Article::STATUS_PUBLISHED) {
            return abort(404);
        }

        $article->load('user', 'media', 'location', 'location.ratings')
            ->withCount('interactions', 'media', 'categories', 'tags', 'views', 'imports')
            // withCount comment where dont have parent_id
            ->withCount(['comments' => function ($query) {
                $query->whereNull('parent_id')
                ->whereHas('user' , function ($query) {
                    $query->where('status', User::STATUS_ACTIVE);
                });
            }]);

        return response()->json([
            'article' => new PublicArticleResource($article)
        ]);
    }

    /**
     * Web - Get Public Articles
     *
     * @param Request $request
     * @return JsonResponse
     *
     * @group Article
     * @urlParam limit integer optional Limit the number of results. Example: 10
     * @response scenario=success {
     * "data": []
     * }
     */
    public function getPublicArticles(Request $request)
    {
        $query = Article::query();

        $query->where('available_for_web', true)
            ->published()
            ->where('visibility', Article::VISIBILITY_PUBLIC);

        // pass to query builder
        $data = $this->articleQueryBuilder($query, $request, false);

        $locationIds = $data->pluck('location.0.id')->filter()->unique()->toArray();

        $storesWithOffers = DB::table('locatables as store_locatables')
            ->whereIn('store_locatables.location_id', $locationIds)
            ->where('store_locatables.locatable_type', Store::class)
            ->join('merchant_offer_stores', 'store_locatables.locatable_id', '=', 'merchant_offer_stores.store_id')
            ->join('merchant_offers', function ($join) {
                $join->on('merchant_offer_stores.merchant_offer_id', '=', 'merchant_offers.id')
                    ->where('merchant_offers.status', '=', MerchantOffer::STATUS_PUBLISHED)
                    ->where('merchant_offers.available_at', '<=', now())
                    ->where('merchant_offers.available_until', '>=', now());
            })
            ->pluck('store_locatables.location_id')
            ->unique();

        $data->each(function ($article) use ($storesWithOffers) {
            if ($article->location->isNotEmpty()) {
                $articleLocationId = $article->location->first()->id;
                $article->has_merchant_offer = $storesWithOffers->contains($articleLocationId);
            } else {
                $article->has_merchant_offer = false;
            }
        });

        $data = $query->paginate($request->has('limit') ? $request->limit : config('app.paginate_per_page'));

        return PublicArticleResource::collection($data);
    }

    /**
     * Web - Get Single Public Article
     *
     * @param Request $request
     * @return JsonResponse
     *
     * @group Article
     * @urlParam id integer optional The id of the article. Example: 1
     * @urlParam slug string optional The slug of the article. Example: my-article
     * @response scenario=success {
     * "article": {}
     * }
     */
    public function getPublicArticleSingle(Request $request)
    {
        $this->validate($request, [
            'id' => 'required_if:slug,null|integer',
            'slug' => 'required_if:id,null|string',
        ]);

        $article = Article::where('available_for_web', true)
            ->published()
            ->where('visibility', Article::VISIBILITY_PUBLIC)
            ->where(function ($query) use ($request) {
                if ($request->has('id')) {
                    $query->where('id', $request->id);
                } else {
                    $query->where('slug', $request->slug);
                }
            })
            ->with('user', 'media', 'location', 'location.ratings')
            ->withCount('interactions', 'media', 'categories', 'tags', 'views', 'imports')
            // withCount comment where dont have parent_id
            ->withCount(['comments' => function ($query) {
                $query->whereNull('parent_id')
                ->whereHas('user' , function ($query) {
                    $query->where('status', User::STATUS_ACTIVE);
                });
            }])
            ->first();

        return response()->json([
            'article' => new PublicArticleResource($article)
        ]);
    }

    /**
     * Get Articles by Keyword ID
     *
     * @param Request $request
     * @return JsonResponse
     *
     * @group Article
     * @urlParam keyword_id ID required The id of the keyword. Example: 1
     * @response scenario=success {
     * "data": []
     * }
     */
    public function getArticlesByKeywordId(Request $request)
    {
        $this->validate($request, [
            'keyword_id' => 'required|integer|exists:search_keywords,id'
        ]);

        $keyword = $request->keyword_id;

        // get associated articles with this keyword
        $articles = Article::whereHas('searchKeywords', function ($query) use ($keyword) {
            $query->where('search_keywords.id', $keyword);
        })
        ->published()
        ->where('visibility', 'public') // public articles only!
        ->whereDoesntHave('hiddenUsers', function ($query) {
            $query->where('user_id', auth()->user()->id);
        })
        ->with('user', 'user.media', 'user.followers', 'comments', 'interactions', 'media', 'categories', 'tags', 'location', 'imports', 'location.state', 'location.country', 'location.ratings')
        ->withCount('interactions', 'media', 'categories', 'tags', 'views', 'imports', 'userFollowings')
        ->withCount(['userFollowers' => function ($query) {
            $query->where('status', User::STATUS_ACTIVE);
        }])
        ->withCount(['comments' => function ($query) {
            $query->whereNull('parent_id')
            ->whereHas('user' , function ($query) {
                $query->where('status', User::STATUS_ACTIVE);
            });
        }])
        ->paginate(config('app.paginate_per_page'));

        // increase keyword hits
        SearchKeyword::find($keyword)->increment('hits');

        return ArticleResource::collection($articles);
    }
}
