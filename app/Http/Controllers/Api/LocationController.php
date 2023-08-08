<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\LocationResource;
use App\Models\Location;
use Illuminate\Http\Request;

class LocationController extends Controller
{
    /**
     * Get Locations
     *
     * @return LocationResource
     * @throws \Illuminate\Validation\ValidationException
     * 
     * @group Location
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
                'message' => 'Location not found'
            ], 404);
        }
        
        // load state, country, articles, ratings, merchantOffers
        $location->load('state', 'country', 'articles', 'ratings', 'merchantOffers');
        return new LocationResource($location);
    }
}
