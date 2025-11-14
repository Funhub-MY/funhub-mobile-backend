<?php

namespace App\Filament\Resources\LocationResource\Pages;

use App\Filament\Resources\LocationResource;
use Filament\Pages\Actions;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class EditLocation extends EditRecord
{
    protected static string $resource = LocationResource::class;

	protected function getHeaderActions(): array
	{
		return [
			Actions\DeleteAction::make(),
		];
	}

	protected function afterSave(): void
	{
		$location = $this->record;

		if ($location->wasChanged('full_address')) {
			try {
				Log::info('[BackendEditLocation] Location Geocoding on Edit: Starting Google API call', [
					'location_id' => $location->id,
					'old_address' => $location->getOriginal('full_address'),
					'new_address' => $location->full_address
				]);

				$response = Http::get('https://maps.googleapis.com/maps/api/geocode/json', [
					'address' => $location->full_address,
					'key' => config('filament-google-maps.key'),
				]);

				Log::info('[BackendEditLocation] Location Geocoding on Edit: API Response', [
					'location_id' => $location->id,
					'status_code' => $response->status(),
					'response_body' => $response->body()
				]);

				if ($response->successful()) {
					$geocodeData = $response->json();

					Log::info('[BackendEditLocation] Location Geocoding on Edit: Result Status', [
						'location_id' => $location->id,
						'google_status' => $geocodeData['status'],
						'result_count' => count($geocodeData['results'] ?? [])
					]);

					if ($geocodeData['status'] === 'OK' && !empty($geocodeData['results'])) {
						$googleId = $geocodeData['results'][0]['place_id'];

						$location->update(['google_id' => $googleId]);

						Log::info('[BackendEditLocation] Location Geocoding on Edit: Google ID Updated', [
							'location_id' => $location->id,
							'old_google_id' => $location->getOriginal('google_id'),
							'new_google_id' => $googleId,
							'formatted_address' => $geocodeData['results'][0]['formatted_address'] ?? 'N/A'
						]);
					} else {
						Log::warning('[BackendEditLocation] Location Geocoding on Edit: No Results Found', [
							'location_id' => $location->id,
							'new_address' => $location->full_address
						]);
					}
				} else {
					Log::error('[BackendEditLocation] Location Geocoding on Edit: API Call Failed', [
						'location_id' => $location->id,
						'status_code' => $response->status(),
						'response_body' => $response->body()
					]);
				}
			} catch (\Exception $e) {
				Log::error('[BackendEditLocation] Location Geocoding on Edit: Unexpected Error', [
					'location_id' => $location->id,
					'error_message' => $e->getMessage(),
					'error_trace' => $e->getTraceAsString(),
					'new_address' => $location->full_address ?? 'Unknown'
				]);
			}
		}

		// Sync lat/lng to attached stores if lat or lng has changed
		if ($location->wasChanged('lat') || $location->wasChanged('lng')) {
			try {
				$stores = $location->stores;

				Log::info('[BackendEditLocation] Syncing location coordinates to attached stores', [
					'location_id' => $location->id,
					'lat' => $location->lat,
					'lng' => $location->lng,
					'store_count' => $stores->count()
				]);

				foreach ($stores as $store) {
					$store->update([
						'lat' => $location->lat,
						'long' => $location->lng
					]);

					Log::info('[BackendEditLocation] Updated store coordinates', [
						'store_id' => $store->id,
						'store_name' => $store->name,
						'new_lat' => $location->lat,
						'new_long' => $location->lng
					]);

					// Make the store searchable after updating coordinates
					\App\Jobs\IndexStore::dispatch($store->id);
				}
			} catch (\Exception $e) {
				Log::error('[BackendEditLocation] Error syncing location coordinates to stores', [
					'location_id' => $location->id,
					'error_message' => $e->getMessage(),
					'error_trace' => $e->getTraceAsString()
				]);
			}
		}
	}
}