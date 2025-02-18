<?php

namespace App\Console\Commands;

use App\Models\Location;
use App\Models\Article;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use GuzzleHttp\Client;
use Carbon\Carbon;

class FindLocationsWithoutGoogleId extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'locations:find-without-google-id 
                            {--from= : Start date for article search (format: Y-m-d)} 
                            {--to= : End date for article search (format: Y-m-d)}
                            {--fix : Attempt to fix locations using Google API}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Find locations linked to articles that do not have Google IDs within a date range';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $fromDate = $this->option('from') ? Carbon::parse($this->option('from')) : null;
        $toDate = $this->option('to') ? Carbon::parse($this->option('to')) : null;
        $shouldFix = $this->option('fix');

        // query locations without google_id that have articles
        $this->info("Searching for articles between " . Carbon::parse($fromDate)->startOfDay() . " and " . Carbon::parse($toDate)->endOfDay());

        $query = Location::whereNull('google_id')
            ->whereHas('articles', function ($query) use ($fromDate, $toDate) {
                $query->whereBetween('articles.created_at', [
                    Carbon::parse($fromDate)->startOfDay(), 
                    Carbon::parse($toDate)->endOfDay()
                ]);
            });

        $locations = $query->get();

        $this->info('Found ' . $locations->count() . ' locations without Google ID');
        
        foreach ($locations as $location) {
            $this->info("\nLocation ID: {$location->id}");
            $this->info("Name: {$location->name}");
            $this->info("Address: {$location->address}");
            $this->info("City: {$location->city}");
            
            $articleCount = $location->articles()
                ->when($fromDate, fn($q) => $q->where('articles.created_at', '>=', Carbon::parse($fromDate)->startOfDay()))
                ->when($toDate, callback: fn($q) => $q->where('articles.created_at', '<=', Carbon::parse($toDate)->endOfDay()))
                ->count();
            
            $this->info("Articles in date range: {$articleCount}");

            if ($shouldFix) {
                $this->info('Attempting to fix with Google API...');
                try {
                    $client = new Client();
                    $result = null;
                    
                    // Try finding by name + city/state first
                    if ($location->name) {
                        $this->info('Searching by name and location...');
                        $searchQuery = $location->name;
                        if ($location->city) {
                            $searchQuery .= ', ' . $location->city;
                        }
                        if ($location->state) {
                            $searchQuery .= ', ' . $location->state->name;
                        }
                        if ($location->country) {
                            $searchQuery .= ', ' . $location->country->name;
                        }

                        $this->info("Search query: {$searchQuery}");
                        
                        $response = $client->get('https://maps.googleapis.com/maps/api/place/textsearch/json', [
                            'query' => [
                                'query' => $searchQuery,
                                'key' => config('filament-google-maps.key'),
                                ]
                        ]);
                        
                        $result = json_decode($response->getBody(), true);
                        $this->info("Google Place: " . json_encode($result));
                    }
                    
                    // If name search failed and we have lat/lng, try reverse geocoding
                    if ((!$result || $result['status'] !== 'OK' || empty($result['results'])) && $location->lat && $location->lng) {
                        $this->info('Name search failed, trying reverse geocoding with lat/lng...');
                        $response = $client->get('https://maps.googleapis.com/maps/api/geocode/json', [
                            'query' => [
                                'latlng' => "{$location->lat},{$location->lng}",
                                'key' => config('filament-google-maps.key'),
                                ]
                        ]);
                        
                        $result = json_decode($response->getBody(), true);
                        $this->info("Google Geocode: " . json_encode($result));
                    }

                    if ($result && $result['status'] === 'OK' && !empty($result['results'])) {
                        $googlePlace = $result['results'][0];
                        
                        // for Place API results, we need to get full details
                        if (isset($googlePlace['place_id']) && !isset($googlePlace['address_components'])) {
                            $detailsResponse = $client->get('https://maps.googleapis.com/maps/api/place/details/json', [
                                'query' => [
                                    'place_id' => $googlePlace['place_id'],
                                    'fields' => 'place_id,address_component,formatted_address',
                                    'key' => config('filament-google-maps.key'),
                                    ]
                            ]);
                            
                            $detailsResult = json_decode($detailsResponse->getBody(), true);
                            if ($detailsResult['status'] === 'OK') {
                                $googlePlace = $detailsResult['result'];
                            }
                        }
                        
                        // Update location with Google data
                        $address = $this->findAddressComponent($googlePlace, 'street_number', 'route');
                        
                        // If no structured address found, use the first part of formatted_address
                        if (!$address && isset($googlePlace['formatted_address'])) {
                            $parts = explode(',', $googlePlace['formatted_address']);
                            $address = trim($parts[0]);
                        }
                        
                        // If still no address, keep the existing one
                        if (!$address) {
                            $address = $location->address;
                        }
                        
                        $city = $this->findAddressComponent($googlePlace, 'locality');
                        if (!$city && isset($googlePlace['formatted_address'])) {
                            $parts = explode(',', $googlePlace['formatted_address']);
                            if (count($parts) > 1) {
                                $city = trim($parts[1]);
                            }
                        }
                        
                        $location->update([
                            'google_id' => $googlePlace['place_id'],
                            'address' => $address,
                            'address_2' => null,
                            'zip_code' => $this->findAddressComponent($googlePlace, 'postal_code'),
                            'city' => $city ?: $location->city,
                        ]);

                        $this->info('✓ Successfully updated with Google data');
                        $this->info("New Google ID: {$location->google_id}");
                    } else {
                        $this->error('× No results found from Google API');
                        $this->info("Google Place: " . json_encode($googlePlace));
                    }
                } catch (\Exception $e) {
                    $this->error('× Error updating location: ' . $e->getMessage());
                    Log::error('[FindLocationsWithoutGoogleId] Error updating location ' . $location->id . ': ' . $e->getMessage());
                }
            }
        }

        return 0;
    }

    /**
     * Find address component from Google API response
     */
    private function findAddressComponent($place, ...$types)
    {
        if (empty($place['address_components'])) {
            return null;
        }

        $values = [];
        foreach ($place['address_components'] as $component) {
            if (count(array_intersect($component['types'], $types)) > 0) {
                $values[] = $component['long_name'];
            }
        }

        return !empty($values) ? implode(' ', $values) : null;
    }
}
