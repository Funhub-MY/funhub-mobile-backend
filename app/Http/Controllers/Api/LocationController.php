<?php

namespace App\Http\Controllers\Api;

use App\Events\RatedStore;
use App\Http\Controllers\Controller;
use App\Http\Resources\LocationResource;
use App\Models\Country;
use App\Models\Location;
use App\Models\State;
use App\Models\Store;
use Illuminate\Http\Request;
use App\Http\Resources\StoreRatingResource;
use Illuminate\Support\Facades\Log;
class LocationController extends Controller
{
    /**
     * Get Locations
     *
     * @return LocationResource
     * @throws \Illuminate\Validation\ValidationException
     *
     * @group Location
     * @bodyParam location_ids array optional Location Ids to Filter. Example [1,2,3]
     * @bodyParam name string optional Search query. Example: "KFC SS15"
     * @bodyParam state_id integer optional Filter by state id. Example: 1
     * @bodyParam country_id integer optional Filter by country id. Example: 1
     * @bodyParam city string optional Filter by city. Example: "Subang Jaya"
     * @bodyParam lat float optional Latitude of User GeoLoc. Example: 3.073065
     * @bodyParam lng float optional Longitude of User GeoLoc. Example: 101.607787
     * @bodyParam radius integer optional Radius (KM) of search if lat,lng provided. Example: 10
     *
     * @response scenario=success {
     *  "data": [],
     *  "links": {},
     *  "meta": {
     *     "current_page": 1,
     *   }
     * }
     */
    public function index(Request $request)
    {
        $query = Location::with('state', 'country', 'ratings')
            ->published();

        if ($request->has('location_ids')) {
            $query->whereIn('id', explode(',', $request->location_ids));
        }

        if ($request->has('name')) {
            $query->where('name', 'like', '%' . $request->get('q') . '%');
        }

        if ($request->has('state_id')) {
            $query->where('state_id', $request->get('state_id'));
        }

        if ($request->has('country_id')) {
            $query->where('country_id', $request->get('country_id'));
        }

        // city
        if ($request->has('city')) {
            $query->whereRaw('LOWER(city) = ?', [strtolower($request->get('city'))]);
        }

        if ($request->has('lat') && $request->has('lng')) {
            $radius = $request->has('radius') ? $request->radius : 10; // 10km default
            // get article where article->location lat,lng is within the radius
            $query->selectRaw('*, ( 6371 * acos( cos( radians(?) ) *
                cos( radians( lat ) )
                * cos( radians( lng ) - radians(?)
                ) + sin( radians(?) ) *
                sin( radians( lat ) ) )
                ) AS distance', [$request->lat, $request->lng, $request->lat])
            ->havingRaw("distance < ?", [$radius]);
        }

        $results = $query->orderBy('name', 'asc')
            ->paginate(config('app.paginate_per_page'));

        return LocationResource::collection($results);
    }

    /**
     * Get One Location
     *
     * Locaiton will auto load state, coutry, articles, merchant offers, ratings
     *
     * @param Location $location
     * @return LocationResource
     *
     * @group Location
     * @urlParam location required Location ID. Example: 1
     * @response scenario=success {
     * "data": {
     * }
     * }
     */
    public function show(Location $location)
    {
        if ($location->status == Location::STATUS_DRAFT) {
            return response()->json([
                'message' => __('messages.error.location_controller.Location_not_found')
            ], 404);
        }

        // load state, country, articles, ratings, merchantOffers
        $location->load('state', 'country', 'articles', 'ratings', 'merchantOffers');
        return new LocationResource($location);
    }

    /**
     * Rate a Location and Create/Update Store
     *
     * This endpoint allows users to rate a location and automatically creates or updates an associated store.
     * If the location doesn't exist, it will be created. Similarly, if no store exists for the location,
     * a new store will be created with the location's details.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     *
     * @group Location
     * @bodyParam lat float required Latitude of the location. Example: 3.073065
     * @bodyParam lng float required Longitude of the location. Example: 101.607787
     * @bodyParam google_id string required|nullable Google Place ID for the location. Example: ChIJN1t_tDeuEmsRUsoyG83frY4
     * @bodyParam name string required Name of the location. Example: KFC SS15
     * @bodyParam address string required|nullable Street address of the location. Example: 47, Jalan SS 15/4g
     * @bodyParam address_2 string optional Additional address details. Example: Subang Jaya
     * @bodyParam postcode string required|nullable Postal code of the location. Example: 47500
     * @bodyParam city string required|nullable City name. Example: Subang Jaya
     * @bodyParam state string required|nullable State name or ID. Example: Selangor
     * @bodyParam country string required|nullable Country name. Example: Malaysia
     * @bodyParam rating integer required Rating value between 1 and 5. Example: 4
     * @bodyParam comment string optional Review comment. Example: Great service and ambiance
     * @bodyParam ratingCategories string optional Comma-separated category IDs for the rating. Example: 1,2,3
     *
     * @response scenario=success {
     *  "success": true,
     *  "message": "Location and store rated successfully",
     *  "data": {
     *    "location": {
     *      "id": 1,
     *      "name": "KFC SS15",
     *      "google_id": "ChIJN1t_tDeuEmsRUsoyG83frY4",
     *      "lat": 3.073065,
     *      "lng": 101.607787,
     *      "address": "47, Jalan SS 15/4g",
     *      "city": "Subang Jaya",
     *      "state": "Selangor",
     *      "country": "Malaysia"
     *    },
     *    "store": {
     *      "id": 1,
     *      "name": "KFC SS15",
     *      "status": "active",
     *      "ratings": 4
     *    },
     *    "rating": {
     *      "id": 1,
     *      "rating": 4,
     *      "comment": "Great service and ambiance",
     *      "categories": [1, 2, 3],
     *      "likes_count": 0,
     *      "dislikes_count": 0
     *    }
     *  }
     * }
     */
    public function postRateLocation(Request $request)
    {
        $request->validate([
            'lat' => 'required|numeric',
            'lng' => 'required|numeric',
            'google_id' => 'required|nullable',
            'address' => 'required|nullable',
            'address_2' => 'nullable',
            'postcode' => 'required|nullable',
            'city' => 'required|nullable',
            'state' => 'required|nullable',
            'country' => 'required|nullable',
            'rating' => 'required|numeric|min:1|max:5',
            'comment' => 'nullable|string',
            'name' => 'required|string',
            'ratingCategories' => 'nullable|string' // comma-separated category IDs
        ]);

        $locationData = $request->all();
        $location = null;
        $locations = null;
        $store = null;

        // first try to find location by google_id
        if (isset($locationData['google_id']) && $locationData['google_id'] != 0) {
            $locations = Location::where('google_id', $locationData['google_id'])->get();
        }

        // if not found by google_id, search by lat/lng
        if (empty($locations) || $locations->isEmpty()) {
            $locations = Location::where('lat', $locationData['lat'])
                ->where('lng', $locationData['lng'])
                ->get();
        }

        // find the most matching location from the results
        if ($locations) {
            foreach ($locations as $keys => $loc) {
                // default to first non-mall location
                if ($keys == 0 && $loc->is_mall == 0) {
                    $location = $loc;
                }

                // check name similarity
                similar_text(strtolower($locationData['name']), strtolower($loc->name), $percentage);

                if ($loc && ($locationData['name'] == $loc->name || $percentage > 90)) {
                    $location = $loc;
                    break;
                }
            }
        }

        // if location doesn't exist, create it
        if (!$location) {
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

            // handle state
            $state = null;
            if (isset($locationData['state'])) {
                if (is_numeric($locationData['state'])) {
                    $state = State::where('id', $locationData['state'])->first();
                } else {
                    $state = State::whereRaw('lower(name) like ?', ['%' . trim(strtolower($locationData['state'])) . '%'])->first();
                }
            }

            if ($state) {
                $loc['state_id'] = $state->id;
                $loc['country_id'] = $state->country_id;
            } else {
                // handle country
                $country = null;
                if (isset($locationData['country']) && $locationData['country'] != 0) {
                    $country = Country::where('name', 'like', '%' . $locationData['country'] . '%')->first();
                }

                if (!$country) {
                    // default to Malaysia if no country specified
                    $country = Country::where('name', 'Malaysia')->first();
                }

                if ($country) {
                    $loc['country_id'] = $country->id;
                }
            }

            $location = Location::create($loc);
        }

        // first create location rating
        $locationRating = $location->ratings()->create([
            'user_id' => auth()->id(),
            'rating' => $request->rating
        ]);

        // find store that has this location as primary location
        $store = Store::whereHas('location', function ($query) use ($location) {
            $query->where('locations.id', $location->id);
        })->first();

        if (!$store) {
            // create new store with same name as location
            Log::info('[LocationController] Creating store for location: ' . $location->name);

            $status = Store::STATUS_ACTIVE;
            // if full address starts with Lorong, Jalan or Street then set to unlisted first
            $smallLetterAddress = trim(strtolower($location->name));
            if (str_starts_with($smallLetterAddress, 'lorong') || str_starts_with($smallLetterAddress, 'jalan') || str_starts_with($smallLetterAddress, 'street')) {
                $status = Store::STATUS_INACTIVE;
            }

            // create store
            $store = Store::create([
                'user_id' => null,
                'name' => $location->name,
                'manager_name' => null,
                'business_phone_no' => null,
                'business_hours' => null,
                'address' => $location->full_address,
                'address_postcode' => $location->zip_code,
                'lang' => $location->lat,
                'long' => $location->lng,
                'is_hq' => false,
                'state_id' => $location->state_id,
                'country_id' => $location->country_id,
                'status' => $status, // all new stores will be inactive first
            ]);

            // also attach the location to the store
            $store->location()->attach($location->id);

            Log::info('[LocationController] Store created for location: ' . $location->id . ' with store id: ' . $store->id);

            // Set this location as the store's primary location
            $store->location()->attach($location->id);
            $store->save();
        }

        // Create store rating linked to location rating
        $rating = $store->storeRatings()->create([
            'user_id' => auth()->id(),
            'rating' => $request->rating,
            'comment' => $request->comment,
            'location_id' => $location->id,
            'created_at' => $locationRating->created_at,
            'updated_at' => $locationRating->updated_at
        ]);

        // handle rating categories if provided
        if ($request->has('ratingCategories')) {
            $categories = explode(',', $request->ratingCategories);
            $rating->ratingCategories()->attach($categories, ['user_id' => auth()->id()]);
        }

        // update store average rating
        $store->ratings = $store->storeRatings()->avg('rating');
        $store->save();

        // load relationships
        $rating->loadCount(['likes', 'dislikes']);
        $rating->load('user', 'ratingCategories');

        // fire RatedStore event
        event(new RatedStore($store, $rating->user));

        return response()->json([
            'success' => true,
            'message' => 'Location and store rated successfully',
            'data' => [
                'location' => $location,
                'store' => $store,
                'rating' => new StoreRatingResource($rating)
            ]
        ]);
    }
}
