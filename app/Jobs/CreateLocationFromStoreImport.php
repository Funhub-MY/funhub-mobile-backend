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
            
            // Get lat/long from store or from data
            $lang = $store->lang ?? ($data['lang'] ?? null);
            $long = $store->long ?? ($data['long'] ?? null);
            
            $locationFromGoogle = null;
            
            // If lat/long not provided, use Google Maps API to get coordinates
            if (!$lang || !$long) {
                $state = State::find($data['state_id']);
                $country = Country::find($data['country_id']);
                $address = $data['address'] . ', ' . ($data['address_postcode'] ?? '') . ', ' . $state->name . ', ' . $country->name;
                
                $client = new Client();
                $response = $client->get('https://maps.googleapis.com/maps/api/geocode/json', [
                    'query' => [
                        'address' => $address,
                        'key' => config('filament-google-maps.key'),
                    ]
                ]);
                
                if ($response->getStatusCode() === 200) {
                    $locationFromGoogle = json_decode($response->getBody(), true);
                    
                    if (isset($locationFromGoogle['results']) && !empty($locationFromGoogle['results'])) {
                        $lang = $locationFromGoogle['results'][0]['geometry']['location']['lat'];
                        $long = $locationFromGoogle['results'][0]['geometry']['location']['lng'];
                        $locationFromGoogle = $locationFromGoogle['results'][0] ?? null;
                        
                        // Update store with lat/long
                        $store->update([
                            'lang' => $lang,
                            'long' => $long
                        ]);
                    }
                }
            }
            
            if ($lang && $long) {
                $location = null;
                $googleId = null;
                
                // If we have Google data, check for existing location by Google ID
                if (isset($locationFromGoogle) && isset($locationFromGoogle['place_id']) && $locationFromGoogle['place_id'] != 0) {
                    $googleId = $locationFromGoogle['place_id'];
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
            }
            
            // Dispatch IndexStore job to make the store searchable
            IndexStore::dispatch($store->id);
            Log::info("[CreateLocationFromStoreImport] IndexStore job dispatched for store: {$store->id} (no location found)");
            
        } catch (\Exception $e) {
            Log::error('[CreateLocationFromStoreImport] Error processing location', [
                'store_id' => $this->storeId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }
    }
}
