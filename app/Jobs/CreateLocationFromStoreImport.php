<?php

namespace App\Jobs;

use App\Jobs\IndexStore;
use App\Models\Country;
use App\Models\Location;
use App\Models\State;
use App\Models\Store;
use GuzzleHttp\Client;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class CreateLocationFromStoreImport implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $storeId;
    protected $storeData;

    /**
     * Create a new job instance.
     *
     * @param int $storeId
     * @param array $storeData
     * @return void
     */
    public function __construct(int $storeId, array $storeData)
    {
        $this->storeId = $storeId;
        $this->storeData = $storeData;

        Log::info('[CreateLocationFromStoreImport] Job instantiated', [
            'store_id' => $storeId,
            'store_name' => $storeData['name'] ?? null
        ]);
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        try {
            $store = Store::find($this->storeId);

            if (!$store) {
                Log::error('[CreateLocationFromStoreImport] Store not found', [
                    'store_id' => $this->storeId
                ]);
                return;
            }

            $data = $this->storeData;
            
            // Get lat/long and Google Place ID from store or from data
            $lang = $store->lang ?? ($data['lang'] ?? null);
            $long = $store->long ?? ($data['long'] ?? null);
            $googlePlaceId = $data['google_place_id'] ?? null;
            
            $locationFromGoogle = null;
            $googleId = null;
            
            // If Google Place ID is provided, use it directly to get place details
            if ($googlePlaceId) {
                $client = new Client();
                $googleApiKey = config('filament-google-maps.key');
                
                try {
                    $placeDetailsQuery = [
                        'place_id' => $googlePlaceId,
                        'fields' => 'geometry,name,formatted_address,address_components',
                        'key' => $googleApiKey,
                    ];
                    
                    Log::info("Fetching place details for Google Place ID: {$googlePlaceId}", ['store_id' => $store->id]);
                    $response = $client->get('https://maps.googleapis.com/maps/api/place/details/json', ['query' => $placeDetailsQuery]);
                    
                    if ($response->getStatusCode() === 200) {
                        $result = json_decode($response->getBody(), true);
                        
                        if (isset($result['result'])) {
                            $placeDetails = $result['result'];
                            $locationFromGoogle = [
                                'geometry' => $placeDetails['geometry'],
                                'formatted_address' => $placeDetails['formatted_address'] ?? '',
                                'address_components' => $placeDetails['address_components'] ?? [],
                            ];
                            
                            // Get coordinates from place details
                            $lang = $placeDetails['geometry']['location']['lat'] ?? $lang;
                            $long = $placeDetails['geometry']['location']['lng'] ?? $long;
                            $googleId = $googlePlaceId;
                            
                            Log::info("Successfully retrieved place details for Google Place ID: {$googlePlaceId}", [
                                'store_id' => $store->id,
                                'lat' => $lang,
                                'lng' => $long
                            ]);
                        } else {
                            Log::error("Place details not found for Google Place ID: {$googlePlaceId}", [
                                'store_id' => $store->id,
                                'status' => $result['status'] ?? 'UNKNOWN'
                            ]);
                        }
                    } else {
                        Log::error("Failed to fetch place details for Google Place ID: {$googlePlaceId}", [
                            'store_id' => $store->id,
                            'status_code' => $response->getStatusCode()
                        ]);
                    }
                } catch (\Exception $e) {
                    Log::error("Exception while fetching place details for Google Place ID: {$googlePlaceId}", [
                        'store_id' => $store->id,
                        'error' => $e->getMessage()
                    ]);
                }
            }
            
            // If lat/long still not available or Google Place ID lookup failed, use Google Maps API to get coordinates
            if ((!$lang || !$long) && !$locationFromGoogle) {
                $client = new Client();
                $googleApiKey = config('filament-google-maps.key');
                $locationFromGoogle = null;
                $searchResult = null;

                // --- First Attempt: Search by Store Name (Malaysia only) ---
                $queryByName = [
                    'address' => $store->name, // Use store name for the first search
                    'components' => 'country:MY', // Restrict to Malaysia
                    'key' => $googleApiKey,
                ];

                Log::info("Attempting geocode by name for store: {$store->name}", ['query' => $queryByName]);
                try {
                    $responseByName = $client->get('https://maps.googleapis.com/maps/api/geocode/json', ['query' => $queryByName]);
                    if ($responseByName->getStatusCode() === 200) {
                        $searchResult = json_decode($responseByName->getBody(), true);
                        Log::info("Geocode by name result", ['status' => $searchResult['status'] ?? 'N/A', 'count' => count($searchResult['results'] ?? [])]);
                        // Check if results are valid and not ZERO_RESULTS
                        if (isset($searchResult['results']) && !empty($searchResult['results']) && $searchResult['status'] !== 'ZERO_RESULTS') {
                             $locationFromGoogle = $searchResult['results'][0] ?? null;
                        } else {
                             Log::warning("Geocode by name failed or returned no results for store: {$store->name}");
                             $searchResult = null; // Reset searchResult if first attempt failed
                        }
                    } else {
                        Log::error("Geocode by name API request failed", ['status_code' => $responseByName->getStatusCode()]);
                    }
                } catch (\Exception $e) {
                    Log::error("Exception during geocode by name", ['error' => $e->getMessage()]);
                }


                // --- Second Attempt: Search by Address (if name search failed or yielded no results) ---
                if (!$locationFromGoogle) {
                    $state = State::find($data['state_id']);
                    $country = Country::find($data['country_id']); // Ensure country is MY or handle appropriately
                    // Construct address string carefully
                    $addressParts = array_filter([
                        $data['address'] ?? null,
                        $data['address_postcode'] ?? null,
                        $data['city'] ?? null, // Added City
                        $state->name ?? null,
                       // $country->name ?? null // Country name is handled by components filter
                    ]);
                    $addressString = implode(', ', $addressParts);

                    if (!empty($addressString)) {
                        $queryByAddress = [
                            'address' => $addressString,
                            'components' => 'country:MY', // Restrict to Malaysia
                            'key' => $googleApiKey,
                        ];

                        Log::info("Attempting geocode by address for store: {$store->name}", ['query' => $queryByAddress]);
                         try {
                            $responseByAddress = $client->get('https://maps.googleapis.com/maps/api/geocode/json', ['query' => $queryByAddress]);
                            if ($responseByAddress->getStatusCode() === 200) {
                                $searchResult = json_decode($responseByAddress->getBody(), true);
                                Log::info("Geocode by address result", ['status' => $searchResult['status'] ?? 'N/A', 'count' => count($searchResult['results'] ?? [])]);
                                // Check if results are valid and not ZERO_RESULTS
                                if (isset($searchResult['results']) && !empty($searchResult['results']) && $searchResult['status'] !== 'ZERO_RESULTS') {
                                     $locationFromGoogle = $searchResult['results'][0] ?? null;
                                } else {
                                     Log::warning("Geocode by address also failed or returned no results for store: {$store->name}");
                                }
                            } else {
                                Log::error("Geocode by address API request failed", ['status_code' => $responseByAddress->getStatusCode()]);
                            }
                        } catch (\Exception $e) {
                            Log::error("Exception during geocode by address", ['error' => $e->getMessage()]);
                        }
                    } else {
                         Log::warning("Address string is empty, skipping geocode by address for store: {$store->name}");
                    }
                }

                // --- Process results if found ---
                if ($locationFromGoogle) {
                    // If we didn't already get lat/long from Google Place ID, extract them from geocoding result
                    if (!$googlePlaceId) {
                        $lang = $locationFromGoogle['geometry']['location']['lat'] ?? null;
                        $long = $locationFromGoogle['geometry']['location']['lng'] ?? null;
                        
                        // Try to get place_id from geocoding result if available
                        $googleId = $locationFromGoogle['place_id'] ?? null;
                    }

                    if ($lang && $long) {
                        // Update store with lat/long
                        Log::info("Updating store {$store->id} with coordinates: lat={$lang}, lng={$long}");
                        $store->update([
                            'lang' => $lang,
                            'long' => $long
                        ]);
                    } else {
                         Log::warning("Extracted lat/lng are invalid from geocode result for store: {$store->id}", ['result' => $locationFromGoogle]);
                    }
                } else {
                    Log::error("Failed to geocode store: {$store->id} ({$store->name}) after both attempts.");
                }
            }
            
            // Now proceed with location creation/linking if we have valid coordinates
            if ($lang && $long) {
                $location = null;
                
                // Check for existing location by Google ID if available
                if ($googleId) {
                    $location = Location::where('google_id', $googleId)->first();
                }
                
                // If no location found by Google ID, check by lat/lng
                if (!$location) {
                    $location = Location::where('lat', $lang)
                        ->where('lng', $long)
                        ->first();
                }
                
                if ($location) {
                    // Update existing location
                    $location->update([
                        'name' => $data['name'],
                        'address' => $data['address'] ?? '',
                        'zip_code' => $data['address_postcode'] ?? '',
                        'city' => $data['city'] ?? '',
                        'state_id' => $data['state_id'],
                        'country_id' => $data['country_id'],
                    ]);
                    
                    // Always update store with location's lat/long
                    $store->update([
                        'lang' => $location->lat,
                        'long' => $location->lng
                    ]);
                } else {
                    // Create new location with Google data if available
                    $locationData = [
                        'name' => $data['name'],
                        'lat' => $lang,
                        'lng' => $long,
                        'address' => $data['address'] ?? '',
                        'zip_code' => $data['address_postcode'] ?? '',
                        'city' => $data['city'] ?? '',
                        'state_id' => $data['state_id'],
                        'country_id' => $data['country_id'],
                        'is_mall' => 0,
                    ];
                    
                    // Add Google ID if available
                    if ($googleId) {
                        $locationData['google_id'] = $googleId;
                    }
                    
                    // Add city from Google if available
                    if (isset($locationFromGoogle) && isset($locationFromGoogle['address_components'])) {
                        $addressComponents = collect($locationFromGoogle['address_components']);
                        $city = $addressComponents->filter(function ($component) {
                            return in_array('locality', $component['types']);
                        })->first();
                        
                        if ($city) {
                            $locationData['city'] = $city['long_name'];
                        }
                    }
                    
                    $location = Location::create($locationData);
                    
                    // Always update store with location's lat/long
                    $store->update([
                        'lang' => $location->lat,
                        'long' => $location->lng
                    ]);
                }
                
                // Attach location to store
                $store->location()->attach($location->id);
                Log::info("[CreateLocationFromStoreImport] Store {$store->id} attached to location: {$location->id}");
                
                // Make sure store has the correct lat/long
                if ($store->lang != $location->lat || $store->long != $location->lng) {
                    $store->update([
                        'lang' => $location->lat,
                        'long' => $location->lng
                    ]);
                    Log::info("[CreateLocationFromStoreImport] Updated store {$store->id} coordinates to match location");
                }
                
                // Dispatch IndexStore job to make the store searchable
                // This ensures indexing happens only after location processing is complete
                IndexStore::dispatch($store->id);
                Log::info("[CreateLocationFromStoreImport] IndexStore job dispatched for store: {$store->id}");
            } else {
                Log::warning("[CreateLocationFromStoreImport] Could not determine lat/long for store {$store->id}");
                
                // Dispatch IndexStore job to make the store searchable even without location
                IndexStore::dispatch($store->id);
                Log::info("[CreateLocationFromStoreImport] IndexStore job dispatched for store: {$store->id} (no location found)");
            }
            
        } catch (\Exception $e) {
            Log::error('[CreateLocationFromStoreImport] Error processing location', [
                'store_id' => $this->storeId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }
    }
}
