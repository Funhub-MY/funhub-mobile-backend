<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\StateResource;
use App\Models\State;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class StateController extends Controller
{
    /**
     * Get States
     * 
     * @return \Illuminate\Http\JsonResponse
     * 
     * @group Other
     * @subgroup State
     * @response scenario="success" {
     * ["id" => 1, "name" => "Abia", "code" => "AB", "country_id" => 1],
     * }
     * 
     */
    public function getStates()
    {
        // Get the preferred language of user, use the default language if not set
        $user = auth()->user();
        $locale = $user->last_lang ?? config('app.locale');

        // Filter out states based on hardcoded list
        $states = [
            'Perlis', 'Kedah', 'Pulau Pinang', 'Perak', 'Pahang', 'Kelantan',
            'Terengganu', 'Selangor', 'W.P. Kuala Lumpur', 'W.P. Putrajaya', 'Negeri Sembilan',
            'Malacca', 'Johor', 'Sabah', 'Sarawak', 'W.P. Labuan', 'Others'
        ];

        $states = State::whereIn('name', $states)
            ->orderBy('name', 'ASC')
            ->get()
            ->map(function ($state) use ($locale) {
                $state->name_translation = json_decode($state->name_translation, true)[$locale] ?? $state->name;
                return $state;
            });
            
        return response()->json(StateResource::collection($states));
    }

    /**
     * Get State by User Location
     * 
     * Get state information based on user's latitude and longitude coordinates
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     * 
     * @group Other
     * @subgroup State
     * 
     * @bodyParam lat numeric required The latitude coordinate. Example: 3.140853
     * @bodyParam lng numeric required The longitude coordinate. Example: 101.693207
     * 
     * @response scenario="success" {
     *     "error": false,
     *     "message": "Success",
     *     "data": {
     *         "id": 1,
     *         "name": {
     *             "en": "Selangor",
     *             "zh": ""
     *         },
     *         "code": "SGR",
     *         "country_id": 1
     *     }
     * }
     * @response status=422 scenario="validation error" {
     *     "message": "The lat field is required. The lng field is required.",
     *     "errors": {
     *         "lat": ["The lat field is required."],
     *         "lng": ["The lng field is required."]
     *     }
     * }
     */
    public function getStateByUserLocation(Request $request)
    {
        // validate request
        $request->validate([
            'lat' => 'required|numeric',
            'lng' => 'required|numeric',
        ]);

        try {
            // get user's preferred language
            $user = auth()->user();
            $locale = $user->last_lang ?? config('app.locale');

            // round coordinates to 2 decimal places (roughly 1.1km precision)
            $roundedLat = round($request->lat, 2);
            $roundedLng = round($request->lng, 2);

            // create cache key based on rounded coordinates
            $cacheKey = "geocode_state_{$roundedLat}_{$roundedLng}";

            // try to get cached state data
            $cachedState = Cache::get($cacheKey);
            if ($cachedState) {
                $stateModel = State::find($cachedState['state_id']);
                if ($stateModel) {
                    $translations = $stateModel->name_translation ? json_decode($stateModel->name_translation, true) : [];
                    return response()->json([
                        'error' => false,
                        'message' => 'Success',
                        'data' => [
                            'id' => $stateModel->id,
                            'name' => [
                                'en' => $translations['en'] ?? $stateModel->name,
                                'zh' => $translations['zh'] ?? $stateModel->name
                            ],
                            'code' => $stateModel->code,
                            'country_id' => $stateModel->country_id
                        ]
                    ]);
                }
            }

            // if not in cache, call Google Maps API
            $client = new \GuzzleHttp\Client();
            $response = $client->get('https://maps.googleapis.com/maps/api/geocode/json', [
                'query' => [
                    'latlng' => $request->lat . ',' . $request->lng,
                    'key' => config('filament-google-maps.key'),
                ]
            ]);

            if ($response->getStatusCode() !== 200) {
                return response()->json([
                    'error' => true,
                    'message' => 'Failed to get location data'
                ], 500);
            }

            $locationData = json_decode($response->getBody(), true);

            // check if we got results
            if (!isset($locationData['results']) || empty($locationData['results'])) {
                return response()->json([
                    'error' => true,
                    'message' => 'No location data found'
                ], 404);
            }

            // extract state from address components
            $state = null;
            foreach ($locationData['results'][0]['address_components'] as $component) {
                if (in_array('administrative_area_level_1', $component['types'])) {
                    $state = $component['long_name'];
                    break;
                }
            }

            if (!$state) {
                return response()->json([
                    'error' => true,
                    'message' => 'State not found in location data'
                ], 404);
            }

            // first try to find state with matching code that has translations
            $stateModel = State::where('name', 'like', '%' . $state . '%')
                ->when(function ($query) use ($state) {
                    // get all states with this code
                    $statesWithCode = State::where('name', 'like', '%' . $state . '%')
                        ->pluck('code')
                        ->filter()
                        ->unique();
                        
                    if ($statesWithCode->isNotEmpty()) {
                        // if we found states with codes, get all states with these codes
                        return $query->whereIn('code', $statesWithCode);
                    }
                    return $query;
                })
                ->orderByRaw("
                    CASE 
                        WHEN name_translation IS NOT NULL 
                        AND JSON_EXTRACT(name_translation, '$.en') != ''
                        AND JSON_EXTRACT(name_translation, '$.zh') != ''
                        THEN 1
                        ELSE 2
                    END
                ")
                ->first();

            if ($stateModel) {
                // If we found a state with this code, look for one with translations
                if ($stateModel->code) {
                    $stateWithTranslations = State::where('code', $stateModel->code)
                        ->whereNotNull('name_translation')
                        ->whereRaw("JSON_EXTRACT(name_translation, '$.zh') != ''")
                        ->first();
                    
                    if ($stateWithTranslations) {
                        $stateModel = $stateWithTranslations;
                    }
                }

                // cache the state data
                Cache::put($cacheKey, ['state_id' => $stateModel->id], now()->addDays(30));

                $translations = $stateModel->name_translation ? json_decode($stateModel->name_translation, true) : [];
                return response()->json([
                    'error' => false,
                    'message' => 'Success',
                    'data' => [
                        'id' => $stateModel->id,
                        'name' => [
                            'en' => $translations['en'] ?? $stateModel->name,
                            'zh' => $translations['zh'] ?? $stateModel->name
                        ],
                        'code' => $stateModel->code,
                        'country_id' => $stateModel->country_id
                    ]
                ]);
            }

            return response()->json([
                'error' => true,
                'message' => 'State not found in our database'
            ], 404);

        } catch (\Exception $e) {
            Log::error('Error in getStateByUserLocation: ' . $e->getMessage());
            return response()->json([
                'error' => true,
                'message' => 'Failed to process location data'
            ], 500);
        }
    }
}
