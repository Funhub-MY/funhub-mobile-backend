<?php

namespace App\Filament\Resources\StoreResource\Pages;

use App\Filament\Resources\StoreResource;
use App\Models\Country;
use App\Models\Location;
use App\Models\Merchant;
use App\Models\State;
use App\Models\Store;
use Filament\Pages\Actions;
use Filament\Resources\Pages\EditRecord;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\Log;

class EditStore extends EditRecord
{
    protected static string $resource = StoreResource::class;

    protected function getActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $data['location_type'] = 'manual';

        $this->record->load('location');


        // check if record has location attached
        if ($this->record->location->count() > 0) {
            $data['location_id'] = $this->record->location->first()->id;
            $data['location_type'] = 'existing';
        }

        // inverse business_hours if there is json data
        if ($data['business_hours']) {
            $data['business_hours'] = collect(json_decode($data['business_hours'], true))->map(function ($item, $key) {
                return [
                    'day' => $key,
                    'open_time' => $item['open_time'],
                    'close_time' => $item['close_time']
                ];
            })->values()->toArray();
        }
        return $data;
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        // if has location_id, attach location to store
        if (isset($data['location_id']) && $data['location_id'] !== null) {
            // detach first
            $this->record->location()->detach();
            // attach again
            $this->record->location()->attach($data['location_id']);
        }

        if ($data['use_store_redeem'] && $data['use_store_redeem'] === true) {
            // check if redeem_code has value
            if (empty($data['redeem_code'])) {
                $data['redeem_code'] = rand(100000, 999999); // check with Merchants redeem_code see if crash
                $tries = 0;
                while (Merchant::where('redeem_code', $data['redeem_code'])->exists()) {
                    $data['redeem_code'] = rand(100000, 999999);
                    $tries++;
                    if ($tries > 10) {
                        Log::error('[Store Filament] Redeem code generation failed after 10 tries');
                        break;
                    }
                }

                // reset loop for Store redeem code
                $tries = 0;
                while (Store::where('redeem_code', $data['redeem_code'])->exists()) {
                    $data['redeem_code'] = rand(100000, 999999);
                    $tries++;
                    if ($tries > 10) {
                        Log::error('[Store Filament] Redeem code generation failed after 10 tries');
                        break;
                    }
                }
            }
        }

        if (isset($data['location_type']) && $data['location_type'] === 'manual') {
            $hasLocationAtttached = $this->record->location()->exists();
            if ($hasLocationAtttached) {
                $this->record->location()->detach();
            }

            // google reverse geoloc search via address first
            $state = State::find($data['state_id']);
            $country = Country::find($data['country_id']);
            $address = $data['address'] . ', ' . $data['address_postcode'] . ', ' . $state->name . ', ' . $country->name;
            // eg. "17, jalan usj 18/4, 47630, Selangor, Malaysia"
            $client = new Client();
            $response = $client->get('https://maps.googleapis.com/maps/api/geocode/json', [
                'query' => [
                    'address' => $address,
                    'key' => config('filament-google-maps.key'),
                ]
            ]);

            $locationFromGoogle = null;
            if ($response->getStatusCode() === 200) {
                // Parse the response
                $locationFromGoogle = json_decode($response->getBody(), true);

                // Check if the response contains results
                if (isset($locationFromGoogle['results']) && !empty($locationFromGoogle['results'])) {
                    $data['lang'] = $locationFromGoogle['results'][0]['geometry']['location']['lat'];
                    $data['long'] = $locationFromGoogle['results'][0]['geometry']['location']['lng'];
                } else {
                    // No results found, keep as null first
                    $data['lang'] = null;
                    $data['long'] = null;
                }

                $locationFromGoogle = $locationFromGoogle['results'][0] ?? null;
                if ($locationFromGoogle) {
                    $location = null;
                    // must create a location data if not exists
                    if (isset($locationFromGoogle['place_id']) && $locationFromGoogle['place_id'] != 0) {
                        $location = Location::where('google_id', $locationFromGoogle['place_id'])->first();
                    } else {
                        // if location cant be found by google_id, then find by lat,lng
                        $location = Location::where('lat', $locationFromGoogle['lat'])
                            ->where('lng', $locationFromGoogle['lng'])
                            ->first();
                    }

                    if (!$location) {
                        $addressComponents = collect($locationFromGoogle['address_components']);
                        $city = $addressComponents->filter(function ($component) {
                            return in_array('locality', $component['types']);
                        })->first(); // grab city out from google

                        // create a new location
                        $location = Location::create([
                            'name' => $data['name'], // store name
                            'google_id' => isset($locationFromGoogle['place_id']) ? $locationFromGoogle['place_id'] : null,
                            'lat' => $data['lang'], // google provided
                            'lng' => $data['long'], // google provided
                            'address' => $data['address'] ?? '', // user provided
                            'zip_code' => $data['address_postcode'] ?? '', // user provided
                            'city' => $city['long_name'] ?? '', // google provided
                            'state_id' => $data['state_id'], // user provided
                            'country_id' => $data['country_id'], // user provided
                        ]);

                        Log::info('[Store Filament] Location created: ' . $location->id);
                    }
                    // attach store to location
                    $this->record->location()->attach($location);
                    Log::info('[Store Filament] Store ' . $this->record->id . 'attached to location: ' . $location->id);
                } else {
                    Log::info('[Store Filament] Failed to get location data from Google Maps API');
                }
            } else {
                Log::info('Failed to get location data from Google Maps API');
            }
        }

        // handles business hours save
        if (count($data['business_hours']) > 0) {
            $data['business_hours'] = json_encode(collect($data['business_hours'])->mapWithKeys(function ($item) {
                return [$item['day'] => [
                    'open_time' => $item['open_time'],
                    'close_time' => $item['close_time']
                ]];
            })->toArray());
        }

        return $data;
    }
}
