<?php

namespace App\Filament\Resources\StoreResource\Pages;

use App\Filament\Actions\CustomImportStoresAction;
use App\Filament\Resources\StoreResource;
use App\Models\Country;
use App\Models\Location;
use App\Models\MerchantCategory;
use App\Models\State;
use App\Models\Store;
use App\Models\User;
use Filament\Pages\Actions;
use Filament\Resources\Pages\ListRecords;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Konnco\FilamentImport\Actions\ImportField;
use Konnco\FilamentImport\Actions\ImportAction;
use pxlrbt\FilamentExcel\Exports\ExcelExport;
use pxlrbt\FilamentExcel\Actions\Pages\ExportAction;
use pxlrbt\FilamentExcel\Columns\Column;
use App\Filament\Actions\SyncStoreCategoriesAction;

class ListStores extends ListRecords
{
    protected static string $resource = StoreResource::class;

    protected function getActions(): array
    {
        return [
            Actions\CreateAction::make(),

			// Import Stores from CSV
			ImportAction::make('importStores')
				->label('Import Stores (csv)')
				->uniqueField('name')
				->fields([
					ImportField::make('name')
						->label('Store Name')
						->required(),
					ImportField::make('address')
						->label('Address')
						->required(),
					ImportField::make('address_postcode')
						->label('Postcode'),
					ImportField::make('city')
						->label('City Name')
						->required(),
					// Allow user to upload state and country name in csv and convert to id by logic
					ImportField::make('state_name')
						->label('State Name')
						->required()
						->mutateBeforeCreate(function ($value) {
							$state = State::where('name', $value)->first();
							if (!$state) {
								throw new \Exception("State not found: {$value}");
							}
							return $state->id;
						}),
					ImportField::make('country_name')
						->label('Country Name')
						->required()
						->mutateBeforeCreate(function ($value) {
							$country = Country::where('name', $value)->first();
							if (!$country) {
								throw new \Exception("Country not found: {$value}");
							}
							return $country->id;
						}),
					ImportField::make('user_id')
						->label('User ID')
						->mutateBeforeCreate(function ($value) {
							if (empty($value)) return null;
							
							$user = User::find($value);
							if (!$user) {
								throw new \Exception("User not found with ID: {$value}");
							}
							return $user->id;
						}),
					ImportField::make('parent_categories')
						->label('Parent Categories (comma separated)')
						->helperText('Enter parent category names separated by commas'),
					ImportField::make('sub_categories')
						->label('Sub Categories (comma separated)')
						->helperText('Enter sub category names separated by commas'),
					ImportField::make('business_phone_no')
						->label('Phone Number')
						->mutateBeforeCreate(function ($value) {
							if (empty($value)) return null;
							// Remove leading 0 or +60 from phone number
							return preg_replace('/^(0|\+60)/', '', $value);
						}),
					ImportField::make('business_hours')
						->label('Business Hours')
						->helperText('Format: day:openTime-closeTime (e.g., 1:8AM-6PM,2:8AM-6PM)')
						->mutateBeforeCreate(function ($value) {
							if (empty($value)) return null;
							
							$formattedHours = [];
							$hoursPairs = explode(',', $value);
							
							foreach ($hoursPairs as $pair) {
								$parts = explode(':', $pair);
								if (count($parts) !== 2) continue;
								
								$day = trim($parts[0]);
								$times = explode('-', $parts[1]);
								if (count($times) !== 2) continue;
								
								$formattedHours[$day] = [
									'open_time' => trim($times[0]),
									'close_time' => trim($times[1])
								];
							}
							
							return json_encode($formattedHours);
						}),
					ImportField::make('rest_hours')
						->label('Rest Hours')
						->helperText('Format: day:startTime-endTime (e.g., 1:12PM-2PM,2:12PM-2PM)')
						->mutateBeforeCreate(function ($value) {
							if (empty($value)) return null;
							
							$formattedHours = [];
							$hoursPairs = explode(',', $value);
							
							foreach ($hoursPairs as $pair) {
								$parts = explode(':', $pair);
								if (count($parts) !== 2) continue;
								
								$day = trim($parts[0]);
								$times = explode('-', $parts[1]);
								if (count($times) !== 2) continue;
								
								$formattedHours[$day] = [
									'open_time' => trim($times[0]),
									'close_time' => trim($times[1])
								];
							}
							
							return json_encode($formattedHours);
						}),
					ImportField::make('is_hq')
						->label('Is HQ?')
						->mutateBeforeCreate(function ($value) {
							$value = strtolower(trim($value));
							$truthyValues = ['yes', 'true', '1', 'active'];
							return in_array($value, $truthyValues) ? 1 : 0;
						}),
					ImportField::make('lang')
						->label('Latitude')
						->mutateBeforeCreate(function ($value) {
							return !empty($value) ? $value : null;
						}),
					ImportField::make('long')
						->label('Longitude')
						->mutateBeforeCreate(function ($value) {
							return !empty($value) ? $value : null;
						}),
				])
				->handleRecordCreation(function ($data) {
					// Format phone number: remove spaces, dashes and ensure it starts with 60
					$businessPhoneNo = null;
					if (!empty($data['business_phone_no'])) {
						// Remove all non-numeric characters
						$phoneNumber = preg_replace('/[^0-9]/', '', $data['business_phone_no']);
						
						// Remove leading 0 if present
						if (substr($phoneNumber, 0, 1) === '0') {
							$phoneNumber = substr($phoneNumber, 1);
						}
						
						$businessPhoneNo = $phoneNumber;
					}
					
					// Create the store record
					$storeData = [
						'name' => $data['name'],
						'address' => $data['address'],
						'slug' => Str::slug($data['name']).rand(1000, 9999),
						'address_postcode' => $data['address_postcode'] ?? null,
						'state_id' => $data['state_name'],
						'country_id' => $data['country_name'],
						'is_hq' => $data['is_hq'],
						'business_phone_no' => $businessPhoneNo,
						'business_hours' => $data['business_hours'] ?? null,
						'rest_hours' => $data['rest_hours'] ?? null,
						'user_id' => $data['user_id'] ?? null,
						'lang' => $data['lang'] ?? null,
						'long' => $data['long'] ?? null,
					];
					
					// Get merchant_id from user_id if available
					if (!empty($data['user_id'])) {
						$user = User::find($data['user_id']);
						if ($user && $user->merchant) {
							$storeData['merchant_id'] = $user->merchant->id;
						}
					}
					
					// Create the store
					$store = Store::create($storeData);
					// Process parent categories
					if (!empty($data['parent_categories'])) {
						$parentCategoryNames = array_map('trim', explode(',', $data['parent_categories']));
						foreach ($parentCategoryNames as $categoryName) {
							$category = MerchantCategory::whereNull('parent_id')
								->where('name', $categoryName)
								->first();
								
							if ($category) {
								$store->categories()->attach($category->id);
							} else {
								Log::warning("Parent category not found: {$categoryName}");
							}
						}
					}
					
					// Process sub categories
					if (!empty($data['sub_categories'])) {
						$subCategoryNames = array_map('trim', explode(',', $data['sub_categories']));
						foreach ($subCategoryNames as $categoryName) {
							$category = MerchantCategory::whereNotNull('parent_id')
								->where('name', $categoryName)
								->first();
								
							if ($category) {
								$store->categories()->attach($category->id);
							} else {
								Log::warning("Sub category not found: {$categoryName}");
							}
						}
					}
					
					// Create or link location
					$lang = !empty($data['lang']) ? $data['lang'] : null;
					$long = !empty($data['long']) ? $data['long'] : null;
					
					// If lat/long not provided, use Google Maps API to get coordinates
					if (!$lang || !$long) {
						$state = State::find($data['state_name']);
						$country = Country::find($data['country_name']);
						$address = $data['address'] . ', ' . ($data['address_postcode'] ?? '') . ', ' . $state->name . ', ' . $country->name;
						
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
								$lang = $locationFromGoogle['results'][0]['geometry']['location']['lat'];
								$long = $locationFromGoogle['results'][0]['geometry']['location']['lng'];
								$locationFromGoogle = $locationFromGoogle['results'][0] ?? null;
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
								'state_id' => $data['state_name'],
								'country_id' => $data['country_name'],
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
								'state_id' => $data['state_name'],
								'country_id' => $data['country_name'],
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
						}
						
						// Attach location to store
						$store->location()->attach($location->id);
						Log::info("Store {$store->id} attached to location: {$location->id}");
					}
					
					// Make store searchable
					$store->searchable();
					
					return $store;
				}),

			// Sync Stores Categories csv
			SyncStoreCategoriesAction::make(),

            // Export Stores csv
            ExportAction::make()
                ->exports([
                    ExcelExport::make()
                        ->label('Export Stores (CSV)')
                        ->withColumns([
                            Column::make('id')->heading('store_id'),
                            Column::make('name')->heading('store_name'),
                            Column::make('user_id')->heading('user_id'),
                            Column::make('business_phone_no')->heading('phone_number'),
                            Column::make('address')->heading('address'),
                            Column::make('address_postcode')->heading('postcode'),
                            Column::make('city')
                                ->heading('city')
                                ->getStateUsing(fn ($record) => $record->location->first()->city ?? ''),
                            Column::make('state_name')
                                ->heading('state_name')
                                ->getStateUsing(fn ($record) => $record->state->name ?? ''),
                            Column::make('country_name')
                                ->heading('country_name')
                                ->getStateUsing(fn ($record) => $record->country->name ?? ''),
                            Column::make('parent_categories')
                                ->heading('parent_categories')
                                ->getStateUsing(fn ($record) => $record->parentCategories->pluck('name')->join(',')),
                            Column::make('sub_categories')
                                ->heading('sub_categories')
                                ->getStateUsing(fn ($record) => $record->childCategories->pluck('name')->join(',')),
                            Column::make('business_hours')
                                ->heading('business_hours')
                                ->getStateUsing(function ($record) {
                                    if (empty($record->business_hours)) return '';
                                    $hours = json_decode($record->business_hours, true);
                                    if (!$hours) return '';
                                    
                                    $formatted = [];
                                    foreach ($hours as $day => $time) {
                                        $formatted[] = $day . ':' . $time['open_time'] . '-' . $time['close_time'];
                                    }
                                    return implode(',', $formatted);
                                }),
                            Column::make('rest_hours')
                                ->heading('rest_hours')
                                ->getStateUsing(function ($record) {
                                    if (empty($record->rest_hours)) return '';
                                    $hours = json_decode($record->rest_hours, true);
                                    if (!$hours) return '';
                                    
                                    $formatted = [];
                                    foreach ($hours as $day => $time) {
                                        $formatted[] = $day . ':' . $time['open_time'] . '-' . $time['close_time'];
                                    }
                                    return implode(',', $formatted);
                                }),
                            Column::make('lang')->heading('latitude'),
                            Column::make('long')->heading('longitude'),
                            Column::make('status')
                                ->heading('status')
                                ->getStateUsing(fn ($record) => Store::STATUS[$record->status]),
                        ])
                        ->withFilename(fn ($resource) => $resource::getModelLabel() . '-' . date('Y-m-d'))
                        ->withWriterType(\Maatwebsite\Excel\Excel::CSV)
                ]),
        ];
    }
}
