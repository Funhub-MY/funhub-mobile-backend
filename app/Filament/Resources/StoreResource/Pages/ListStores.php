<?php

namespace App\Filament\Resources\StoreResource\Pages;

use App\Filament\Actions\CustomImportStoresAction;
use App\Filament\Resources\StoreResource;
use App\Jobs\CreateLocationFromStoreImport;
use App\Models\Country;
use App\Models\FailedStoreImport;
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
					ImportField::make('is_appointment_only')
						->label('Appointment Only')
						->helperText('Enter true/1/yes if appointment only, otherwise false/0/no or leave empty')
						->mutateBeforeCreate(function ($value) {
							// Convert common truthy string values to boolean 1, default to 0 (false)
							$trueValues = ['true', '1', 'yes'];
							return in_array(strtolower(trim($value)), $trueValues) ? 1 : 0;
						}),
					ImportField::make('business_hours')
						->label('Business Hours')
						->helperText('Format: day:openTime-closeTime (e.g., 1:09:00-18:00|2:09:00-18:00)')
						->mutateBeforeCreate(function ($value) {
							// Check if value is empty or appears to be a JSON string already
							if (empty($value) || $value === '[]') {
								Log::warning('Empty business hours or already JSON', ['value' => $value]);
								return null;
							}
							
							$formattedHours = [];
							$hoursPairs = explode('|', $value); // Using pipe as separator for CSV compatibility
							
							foreach ($hoursPairs as $pair) {
								// Check if the pair contains both a day and time information
								if (strpos($pair, ':') === false || strpos($pair, '-') === false) {
									Log::warning('Invalid business hours format (missing : or -)', ['pair' => $pair]);
									continue;
								}
								
								// Extract the day (everything before the first colon)
								$day = trim(substr($pair, 0, strpos($pair, ':')));
								
								// Extract the time part (everything after the first colon)
								$timePart = substr($pair, strpos($pair, ':') + 1);
								
								// Split the time part by the dash to get open and close times
								$times = explode('-', $timePart);
								if (count($times) !== 2) {
									Log::warning('Invalid time format in business hours', ['times' => $timePart]);
									continue;
								}
								
								// Add to formatted hours
								$formattedHours[$day] = [
									'open_time' => trim($times[0]),
									'close_time' => trim($times[1])
								];
							}
							
							$jsonResult = json_encode($formattedHours);
							return $jsonResult;
						}),
					ImportField::make('rest_hours')
						->label('Rest Hours')
						->helperText('Format: day:startTime-endTime (e.g., 1:12:00-14:00|2:12:00-14:00)')
						->mutateBeforeCreate(function ($value) {
							// Check if value is empty or appears to be a JSON string already
							if (empty($value) || $value === '[]') {
								Log::warning('Empty rest hours or already JSON', ['value' => $value]);
								return null;
							}
							
							$formattedHours = [];
							$hoursPairs = explode('|', $value); // Using pipe as separator for CSV compatibility
							
							foreach ($hoursPairs as $pair) {
								// Check if the pair contains both a day and time information
								if (strpos($pair, ':') === false || strpos($pair, '-') === false) {
									Log::warning('Invalid rest hours format (missing : or -)', ['pair' => $pair]);
									continue;
								}
								
								// Extract the day (everything before the first colon)
								$day = trim(substr($pair, 0, strpos($pair, ':')));
								
								// Extract the time part (everything after the first colon)
								$timePart = substr($pair, strpos($pair, ':') + 1);
								
								// Split the time part by the dash to get open and close times
								$times = explode('-', $timePart);
								if (count($times) !== 2) {
									Log::warning('Invalid time format in rest hours', ['times' => $timePart]);
									continue;
								}
								
								// Add to formatted hours
								$formattedHours[$day] = [
									'open_time' => trim($times[0]),
									'close_time' => trim($times[1])
								];
							}
							
							$jsonResult = json_encode($formattedHours);
							return $jsonResult;
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
					ImportField::make('google_place_id')
						->label('Google Place ID')
						->helperText('Optional. If provided, will be used to create location directly without geocoding')
						->mutateBeforeCreate(function ($value) {
							return !empty($value) ? $value : null;
						}),
				])
				->handleRecordCreation(function ($data) {
					// Check if a store with the exact same name already exists (case sensitive)
					$existingStore = Store::where('name', $data['name'])->first();
					if ($existingStore) {
						// Store already exists, create a failed store import record
						$failedImport = FailedStoreImport::create([
							'name' => $data['name'],
							'address' => $data['address'] ?? null,
							'address_postcode' => $data['address_postcode'] ?? null,
							'city' => $data['city'] ?? null,
							'state_id' => $data['state_name'] ?? null,
							'country_id' => $data['country_name'] ?? null,
							'business_phone_no' => $data['business_phone_no'] ?? null,
							'business_hours' => $data['business_hours'] ?? null,
							'rest_hours' => $data['rest_hours'] ?? null,
							'is_appointment_only' => $data['is_appointment_only'] ?? false,
							'user_id' => $data['user_id'] ?? null,
							'merchant_id' => null,
							'google_place_id' => $data['google_place_id'] ?? null,
							'lang' => $data['lang'] ?? null,
							'long' => $data['long'] ?? null,
							'parent_categories' => $data['parent_categories'] ?? null,
							'sub_categories' => $data['sub_categories'] ?? null,
							'is_hq' => $data['is_hq'] ?? false,
							'failure_reason' => 'Store with this name already exists (ID: ' . $existingStore->id . ')',
							'original_data' => json_encode($data),
						]);
						
						Log::info("Store import failed - duplicate name", [
							'failed_import_id' => $failedImport->id,
							'store_name' => $data['name'],
							'existing_store_id' => $existingStore->id
						]);
						
						// Return the existing store to prevent creating a duplicate
						return $existingStore;
					}
					
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
						'is_appointment_only' => $data['is_appointment_only'] ?? null,
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
					Log::info("Store created", [
						'store_id' => $store->id,
						'store_name' => $store->name
					]);
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
					
					// Store the lat/long if provided directly in the import
					if (!empty($data['lang']) && !empty($data['long'])) {
						$store->update([
							'lang' => $data['lang'],
							'long' => $data['long']
						]);
					}
					
					// Dispatch job to create or link location asynchronously
					// This prevents timeout during large imports
					CreateLocationFromStoreImport::dispatch($store->id, [
						'name' => $data['name'],
						'address' => $data['address'],
						'address_postcode' => $data['address_postcode'] ?? null,
						'city' => $data['city'] ?? null,
						'state_id' => $data['state_name'],
						'country_id' => $data['country_name'],
						'lang' => $data['lang'] ?? null,
						'long' => $data['long'] ?? null,
						'google_place_id' => $data['google_place_id'] ?? null,
					]);
					
					Log::info("CreateLocationFromStoreImport job dispatched for store: {$store->id}");
					
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
                                    return implode('|', $formatted); // Using pipe as separator for CSV compatibility
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
                                    return implode('|', $formatted); // Using pipe as separator for CSV compatibility
                                }),
                            Column::make('lang')->heading('latitude'),
                            Column::make('long')->heading('longitude'),
                            Column::make('status')
                                ->heading('status')
                                ->getStateUsing(fn ($record) => Store::STATUS[$record->status]),
                            Column::make('is_appointment_only')
                                ->heading('is_appointment_only')
                                ->getStateUsing(fn ($record) => $record->is_appointment_only),
                        ])
                        ->withFilename(fn ($resource) => $resource::getModelLabel() . '-' . date('Y-m-d'))
                        ->withWriterType(\Maatwebsite\Excel\Excel::CSV)
                ]),
        ];
    }
}
