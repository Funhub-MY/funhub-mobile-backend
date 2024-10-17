<?php

namespace App\Jobs;

use App\Models\User;
use App\Models\UserHistoricalLocation;
use GuzzleHttp\Client;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class PopulateLocationAddressForUser implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $historicalLocation;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct(UserHistoricalLocation $location)
    {
        $this->historicalLocation = $location;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $client = new Client();
        $response = $client->get('https://maps.googleapis.com/maps/api/geocode/json', [
            'query' => [
                'latlng' => $this->historicalLocation->lat . ',' . $this->historicalLocation->lng,
                'key' => config('filament-google-maps.key'),
            ]
        ]);

        $locationFromGoogle = null;
        if ($response->getStatusCode() === 200) {
            // parse the response
            $locationFromGoogle = json_decode($response->getBody(), true);

            Log::info('[PopulateLocationAddressForUser] Location data from Google Maps API: ' . json_encode($locationFromGoogle), [
                'lat' => $this->historicalLocation->lat,
                'lng' => $this->historicalLocation->lng,
                'history_location_id' => $this->historicalLocation->id,
            ]);

            // check if the response contains results
            if (isset($locationFromGoogle['results']) && !empty($locationFromGoogle['results'])) {
                $addressComponents = collect($locationFromGoogle['results'][0]['address_components']);

                $data = [
                    'address' => $addressComponents->first(fn ($component) => $component['types'][0] == 'street_number')['long_name'] . ' ' . $addressComponents->first(fn ($component) => $component['types'][0] == 'route')['long_name'],
                    'address_2' => $addressComponents->first(fn ($component) => in_array('sublocality', $component['types']))['long_name'],
                    'zip_code' => $addressComponents->first(fn ($component) => $component['types'][0] == 'postal_code')['long_name'],
                    'city' => $addressComponents->first(fn ($component) => $component['types'][0] == 'locality')['long_name'],
                    'state' => $addressComponents->first(fn ($component) => ($component['types'][0] == 'administrative_area_level_1'))['long_name'],
                    'country' => $addressComponents->first(fn ($component) => ($component['types'][0] == 'country'))['long_name'],
                    'google_id' => $locationFromGoogle['results'][0]['place_id'],
                ];

                $this->historicalLocation->update($data);

                Log::info('[PopulateLocationAddressForUser] Location updated and historical record created', [
                    'lat' => $this->historicalLocation->lat,
                    'lng' => $this->historicalLocation->lng,
                    'history_location_id' => $this->historicalLocation->id,
                    'data' => $data,
                ]);
            }
        } else {
            Log::info('[PopulateLocationAddressForUser] Failed to get location data from Google Maps API');
        }
    }
}
