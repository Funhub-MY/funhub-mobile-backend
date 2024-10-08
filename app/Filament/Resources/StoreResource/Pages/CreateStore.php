<?php

namespace App\Filament\Resources\StoreResource\Pages;

use App\Filament\Resources\StoreResource;
use App\Models\Country;
use App\Models\Location;
use App\Models\State;
use App\Models\Store;
use Filament\Pages\Actions;
use Filament\Resources\Pages\CreateRecord;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\Log;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class CreateStore extends CreateRecord
{
    protected static string $resource = StoreResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
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

    protected function handleRecordCreation(array $data): Model
    {
        $merchant = $this->getModel()::create($data);

        // create menus
        if (isset($data['menus'])) {
            foreach ($data['menus'] as $menu) {
                // add from url to media collection with custom properties $menu['name'] then remove from file
                $merchant->addMediaFromDisk($menu['file'])
                    ->withCustomProperties(['name' => $menu['name']])
                    ->toMediaCollection(Store::MEDIA_COLLECTION_MENUS);

                // remove $menu['file'] from storage as moved to spatiemedialibrary
                Storage::delete($menu['file']);
            }
        }

        return $merchant;
    }

    protected function afterCreate(): void
    {
        $data = $this->form->getState();

        // If location_id is present, attach the location to the store
        if (isset($data['location_id']) && $data['location_id'] !== null) {
            $this->record->location()->attach($data['location_id']);
        }

        // If location_type is 'manual', create a new location and attach it to the store
        if (isset($data['location_type']) && $data['location_type'] === 'manual') {
            $state = State::find($data['state_id']);
            $country = Country::find($data['country_id']);
            $address = $data['address'] . ', ' . $data['address_postcode'] . ', ' . $state->name . ', ' . $country->name;

            $client = new Client();
            $response = $client->get('https://maps.googleapis.com/maps/api/geocode/json', [
                'query' => [
                    'address' => $address,
                    'key' => config('filament-google-maps.key'),
                ]
            ]);

            $locationFromGoogle = null;
            if ($response->getStatusCode() === 200) {
                $locationFromGoogle = json_decode($response->getBody(), true);

                if (isset($locationFromGoogle['results']) && !empty($locationFromGoogle['results'])) {
                    $data['lang'] = $locationFromGoogle['results'][0]['geometry']['location']['lat'];
                    $data['long'] = $locationFromGoogle['results'][0]['geometry']['location']['lng'];
                }
                $locationFromGoogle = $locationFromGoogle['results'][0] ?? null;
                if ($locationFromGoogle) {
                    $location = null;

                    if (isset($locationFromGoogle['place_id']) && $locationFromGoogle['place_id'] != 0) {
                        $location = Location::where('google_id', $locationFromGoogle['place_id'])->first();
                    } else {
                        $location = Location::where('lat', $locationFromGoogle['lat'])
                            ->where('lng', $locationFromGoogle['lng'])
                            ->first();
                    }

                    if (!$location) {
                        $addressComponents = collect($locationFromGoogle['address_components']);
                        $city = $addressComponents->filter(function ($component) {
                            return in_array('locality', $component['types']);
                        })->first();

                        $location = Location::create([
                            'name' => $data['name'],
                            'google_id' => isset($locationFromGoogle['place_id']) ? $locationFromGoogle['place_id'] : null,
                            'lat' => $data['lang'],
                            'lng' => $data['long'],
                            'address' => $data['address'] ?? '',
                            'zip_code' => $data['address_postcode'] ?? '',
                            'city' => $city['long_name'] ?? '',
                            'state_id' => $data['state_id'],
                            'country_id' => $data['country_id'],
                        ]);

                        Log::info('[Store Filament] Location created: ' . $location->id);
                    }

                    $this->record->location()->attach($location);
                    Log::info('[Store Filament Create] Store ' . $this->record->id . ' attached to location: ' . $location->id);
                } else {
                    Log::info('[Store Filament Create] Failed to get location data from Google Maps API');
                }
            } else {
                Log::info('Failed to get location data from Google Maps API');
            }
        }
        $this->record->searchable();
    }
}
