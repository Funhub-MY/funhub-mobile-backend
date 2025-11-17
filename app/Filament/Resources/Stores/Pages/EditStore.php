<?php

namespace App\Filament\Resources\Stores\Pages;

use Filament\Actions\DeleteAction;
use App\Services\SyncMerchantPortal;
use App\Filament\Resources\Stores\StoreResource;
use App\Models\Country;
use App\Models\Location;
use App\Models\Merchant;
use App\Models\State;
use App\Models\Store;
use Filament\Pages\Actions;
use Filament\Resources\Pages\EditRecord;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class EditStore extends EditRecord
{
    protected static string $resource = StoreResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $data['location_type'] = 'manual';

        $this->record->load('location');


        // check if record has location attached
        if ($this->record->location->count() > 0) {
			$location = $this->record->location->first();
			$data['location_id'] = $this->record->location->first()->id;
            $data['location_type'] = 'existing';
			$data['city'] = $location->city;
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

        if ($data['rest_hours']) {
            $data['rest_hours'] = collect(json_decode($data['rest_hours'], true))->map(function ($item, $key) {
                return [
                    'day' => $key,
                    'open_time' => $item['open_time'],
                    'close_time' => $item['close_time']
                ];
            })->values()->toArray();
        }

        $data['menus'] = $this->record->getMedia(Merchant::MEDIA_COLLECTION_MENUS)->map(function ($item, $index) {
            return [
                'name' => (isset($item->custom_properties['name'])) ? $item->custom_properties['name'] : 'Menu ' . ($index + 1),
                'file' => $item->getPath(),
            ];
        });
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
                // Log::info('[Store Filament] Store ' . $this->record->id . ' attached to location: ' . $location->id);

                Location::updateOrInsert(
                    [
                        'name' => $data['name'],
                        'address' => $data['address'] ?? '',
                        'zip_code' => $data['address_postcode'] ?? '',
                        'city' => $data['city'] ?? '',
                        'state_id' => $data['state_id'],
                        'country_id' => $data['country_id']
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

            }else {
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

		// Remove leading 0 or +60 from phone number
		if (!empty($data['business_phone_no'])) {
			$data['business_phone_no'] = preg_replace('/^(0|\+60)/', '', $data['business_phone_no']);
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

        if (count($data['rest_hours']) > 0) {
            $data['rest_hours'] = json_encode(collect($data['rest_hours'])->mapWithKeys(function ($item) {
                return [$item['day'] => [
                    'open_time' => $item['open_time'],
                    'close_time' => $item['close_time']
                ]];
            })->toArray());
        }

        if (isset($data['menus'])) {
            $disk = config('filesystems.default');
            if ($disk == 's3') {
                // use s3_public
                $disk = 's3_public';
            }

            Log::info('Menus: ', ['menus' => $data['menus']]);
            // Save existing menu files to a temporary directory
            $tempDir = Storage::disk($disk)->path('temp/' . Str::random(10));
            Storage::disk($disk)->makeDirectory($tempDir);

            $existingMenus = [];
            foreach ($this->record->getMedia(Merchant::MEDIA_COLLECTION_MENUS) as $media) {
                $tempFilePath = $tempDir . '/' . $media->file_name;
                $fileContents = Storage::disk($disk)->get($media->getPath());
                Storage::disk($disk)->put($tempFilePath, $fileContents);
                $existingMenus[$media->file_name] = [
                    'path' => $tempFilePath,
                    'custom_properties' => $media->custom_properties,
                ];
            }

            foreach ($data['menus'] as $index => $menu) {
                $file = $menu['file'];

                // Check if the file exists in the temporary directory or on the storage disk
                if (isset($existingMenus[$file])) {
                    $filePath = $existingMenus[$file]['path'];
                    $customProperties = array_merge($existingMenus[$file]['custom_properties'], ['name' => $menu['name']]);
                } elseif (Storage::disk($disk)->exists($file)) {
                    $filePath = Storage::disk($disk)->path($file);
                    $customProperties = ['name' => $menu['name']];
                } else {
                    // Skip the file if it doesn't exist
                    Log::warning('File not found: ', ['file' => $file]);
                    continue;
                }

                $media = $this->record->addMediaFromDisk($filePath, $disk)
                    ->withCustomProperties($customProperties)
                    ->toMediaCollection(Merchant::MEDIA_COLLECTION_MENUS);

                Log::info('File uploaded: ', ['media' => $media, 'file' => $file]);

                // Delete the file from the temporary directory if it exists
                if (isset($existingMenus[$file])) {
                    Storage::disk($disk)->delete($existingMenus[$file]['path']);
                }

                // Set the order of the media item
                $media->order_column = $index + 1;
                $media->save();
            }

            // Delete the temporary directory
            Storage::deleteDirectory($tempDir);

            // Clear the media collection after processing all files
            $this->record->clearMediaCollection(Merchant::MEDIA_COLLECTION_MENUS);
        } else {
            $this->record->clearMediaCollection(Merchant::MEDIA_COLLECTION_MENUS);
        }

        return $data;
    }

    protected function afterSave(): void
    {
        if (isset($this->record)) {
            // trigger searcheable to reindex
            $this->record->searchable();

            //  Call the merchant portal api to sync (Send signal to merchant portal)
            $syncMerchantPortal = app(SyncMerchantPortal::class);
            $syncMerchantPortal->syncStore($this->record->id);
        }
    }
}
