<?php

namespace App\Console\Commands;

use App\Models\CityName;
use App\Models\Location;
use Illuminate\Console\Command;
use Google\Client;
use Google\Service\Geocoding\GeocodingService;
use Google\Service\Geocoding\GeocoderRequest;
use Illuminate\Support\Facades\Http;

class PopulateCityNames extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'city-names:populate';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Populate cities and city names from locations';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $locations = Location::whereNull('city_id')->get();
        $apiCalls = 0;

        foreach ($locations as $location) {
            $cityName = trim($location->city);

            if (empty($cityName)) {
                continue;
            }

            $this->info('--- Processing name: ' . $cityName . ' for location ID: ' . $location->id);

            // Search for similar city names in the cities table
            $similarCityNames = CityName::where('name', $cityName)->get();

            if ($similarCityNames->count() > 0) {
                $city = $similarCityNames->first()->city;
                $location->city_id = $city->id;
                $location->save();
                $this->info('City ID' . $location->city_id . ' linked to location ID: ' . $location->id);
            } else {
                // If no similar city names found, use the Google Geocoding API
                $response = Http::get('https://maps.google.com/maps/api/geocode/json', [
                    'address' => $cityName,
                    'language' => 'en_US',
                    'key' => config('filament-google-maps.key'),
                ]);
                $data = $response->json();
                $apiCalls++;

                if ($data['status'] === 'OK' && count($data['results']) > 0) {
                    $result = $data['results'][0];
                    $standardizedCityName = $result['address_components'][0]['long_name'];

                    $this->info('Standardized city name: ' . $standardizedCityName);

                    $cityNames = CityName::where('name', $standardizedCityName)->get();
                    $city = null;

                    if ($cityNames->count() > 0) {
                        $city = $cityNames->first()->city;
                        $location->city_id = $city->id;
                        $location->save();
                        $this->info('City ID' . $location->city_id . ' linked to location ID: ' . $location->id);
                    } else {
                        // even standardised names not found, just create it
                        $city = $location->cityLinked()->create([
                            'name' => $standardizedCityName
                        ]);

                        $city->names()->create([
                            'name' => $standardizedCityName
                        ]);
                        $this->info('City name '. $standardizedCityName .' created for city ID: ' . $city->id);
                    }

                    // also need to create if standardized city name not equal to city name
                    // eg. Federal Territory of Kuala Lumpur not equal to 吉隆坡
                    if ($cityName !== $standardizedCityName && $city->names()->where('name', $cityName)->count() === 0) {
                        $city->names()->create([
                            'name' => $cityName
                        ]);

                        $this->info('City name '. $cityName .' created for city ID: ' . $city->id);
                    }
                } else {
                    $this->warn('No results found for city name: ' . $cityName);
                }
            }
        }

        $this->info('API calls made: ' . $apiCalls);

        return Command::SUCCESS;
    }
}
