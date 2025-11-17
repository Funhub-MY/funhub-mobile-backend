<?php

namespace App\Filament\Resources\Stores\Pages;

use App\Services\SyncMerchantPortal;
use App\Filament\Resources\Stores\StoreResource;
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
		// Remove leading 0 or +60 from phone number
		if (!empty($data['business_phone_no'])) {
			$data['business_phone_no'] = preg_replace('/^(0|\+60)/', '', $data['business_phone_no']);
		}

		if (count($data['business_hours']) > 0) {
            $data['business_hours'] = json_encode(collect($data['business_hours'])->mapWithKeys(function ($item) {
                return [$item['day'] => [
                    'open_time' => $item['open_time'],
                    'close_time' => $item['close_time']
                ]];
            })->toArray());
        }

        if (count($data['rest_hours']) > 0) {
            $data['rest_hours'] = json_encode(collect($data['rest_hours'])->mapWithKeys(function ($item) {
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
        if (is_array($data['rest_hours'])) {
            // json encode rest hours
            $data['rest_hours'] = json_encode(collect($data['rest_hours'])->mapWithKeys(function ($item) {
                return [$item['day'] => [
                    'open_time' => $item['open_time'],
                    'close_time' => $item['close_time']
                ]];
            })->toArray());
        }

        if (is_array($data['business_hours'])) {
            // json encode business hours
            $data['business_hours'] = json_encode(collect($data['business_hours'])->mapWithKeys(function ($item) {
                return [$item['day'] => [
                    'open_time' => $item['open_time'],
                    'close_time' => $item['close_time']
                ]];
            })->toArray());
        }

        $merchant = $this->getModel()::create($data);

        // create menus
        if (isset($data['menus']) && count($data['menus']) > 0) {
            foreach ($data['menus'] as $menu) {
                if (!isset($menu['file'])) continue;

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

			$lang = isset($data['lang']) && $data['lang'] !== 0 ? $data['lang'] : null;
			$long = isset($data['long']) && $data['long'] !== 0 ? $data['long'] : null;

			if ($lang && $long) {
				// $location = Location::create([
				// 	'name' => $data['name'],
				// 	'lat' => $lang,
				// 	'lng' => $long,
				// 	'address' => $data['address'] ?? '',
				// 	'zip_code' => $data['address_postcode'] ?? '',
				// 	'city' => $data['city'] ?? '',
				// 	'state_id' => $data['state_id'],
				// 	'country_id' => $data['country_id'],
				// ]);
				// Log::info('[Store Filament] Location created: ' . $location->id);

				// $this->record->location()->attach($location);
				// Log::info('[Store Filament Create] Store ' . $this->record->id . ' attached to location: ' . $location->id);

				Location::updateOrInsert(
                    [
                        'name' => $data['name'],
                        'address' => $data['address'] ?? '',
                        'zip_code' => $data['address_postcode'] ?? '',
                        'city' => $data['city'] ?? '',
                        'state_id' => $data['state_id'],
                        'country_id' => $data['country_id'],
                    ],
                    [
                        'lat' => $lang,
                        'lng' => $long
                    ]);

                    // Fetch the location record to attach it
                $location = Location::where('name', $data['name'])
                    ->where('address', $data['address'] ?? '')
                    ->where('zip_code', $data['address_postcode'] ?? '')
                    ->where('city', $data['city'] ?? '')
                    ->where('state_id', $data['state_id'])
                    ->where('country_id', $data['country_id'])
                    ->first();

                // Attach the location to the record
                if ($location) {
                    Log::info('[Store Filament] Location created: ' . $location->id);
                    $this->record->location()->attach($location->id);
                    Log::info('[Store Filament] Store ' . $this->record->id . ' attached to location: ' . $location->id);
                }
			} else {
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
        //  (KENNETH)
        }else if (isset($data['location_type']) && $data['location_type'] === 'googlemap') {
            $hasLocationAtttached = $this->record->location()->exists();
            if ($hasLocationAtttached) {
                $this->record->location()->detach();
            }

            $lang = isset($data['lang']) && $data['lang'] !== 0 ? $data['lang'] : null;
            $long = isset($data['long']) && $data['long'] !== 0 ? $data['long'] : null;

            if ($lang && $long) {
                $existingLoc = null;
                $existingLocation = Location::where('lat', $lang)
                    ->where('lng', $long)
                    ->get();
                    // ->where('is_mall', 0)
                    // ->first();

                if ($existingLocation) {
                    foreach($existingLocation as $keys => $loc){
                        //  By default, the first location is not set to a mall (Mainly for landscape location). If it is a mall, it will be skipped, and the location will be assigned based on the name.
                        if($keys == 0 && $loc->is_mall == 0){
                            $existingLoc = $loc;
                        }

                        //  Calculate the percentage of similar both text
                        similar_text(strtolower($data['name']), strtolower($loc->name), $percentage);

                        if ($loc && (strtolower($data['name']) == strtolower($loc->name) || $percentage > 90)) {
                            $existingLoc = $loc;
                            break;
                        }
                    }
                }

                if(!empty($existingLoc)) {
                    $existingLoc->update([
                        'name' => $data['name'],
                        'address' => $data['address'] ?? '',
                        'zip_code' => $data['address_postcode'] ?? '',
                        'city' => $data['city'] ?? '',
                        'state_id' => $data['state_id'],
                        'country_id' => $data['country_id'],
                        'is_mall' => $data['is_mall']
                    ]);

                    $location = $existingLoc; // Assign updated location to $location
                } else {
                    // Insert a new record
                    $location = Location::create([
                        'lat' => $lang,
                        'lng' => $long,
                        'name' => $data['name'],
                        'address' => $data['address'] ?? '',
                        'zip_code' => $data['address_postcode'] ?? '',
                        'city' => $data['city'] ?? '',
                        'state_id' => $data['state_id'],
                        'country_id' => $data['country_id'],
                        'is_mall' => $data['is_mall'], // Add the default value
                    ]);
                }

                // Attach the location to the record
                if ($location) {
                    Log::info('[Store Filament] Location created: ' . $location->id);
                    $this->record->location()->attach($location->id);
                    Log::info('[Store Filament] Store ' . $this->record->id . ' attached to location: ' . $location->id);
                }
            }
        } 

        $this->record->searchable();


        //  Call the merchant portal api to sync (Send signal to merchant portal)
        $syncMerchantPortal = app(SyncMerchantPortal::class);
        $syncMerchantPortal->syncStore($this->record->id);
    }
}
