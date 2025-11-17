<?php

namespace App\Filament\Resources\Locations\Pages;

use Exception;
use App\Jobs\CreateStoreFromLocation;
use App\Filament\Resources\Locations\LocationResource;
use Filament\Pages\Actions;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class CreateLocation extends CreateRecord
{
	protected static string $resource = LocationResource::class;

	protected function afterCreate(): void
	{
		$location = $this->record;

		if (!$location->full_address) {
			return;
		}

		try {
			Log::info('[BackendCreateLocation] Location Geocoding: Starting Google API call', [
				'location_id' => $location->id,
				'address' => $location->full_address
			]);

			$response = Http::get('https://maps.googleapis.com/maps/api/geocode/json', [
				'address' => $location->full_address,
				'key' => config('filament-google-maps.key'),
			]);

			Log::info('[BackendCreateLocation] Location Geocoding: API Response', [
				'location_id' => $location->id,
				'status_code' => $response->status(),
				'response_body' => $response->body()
			]);

			if ($response->successful()) {
				$geocodeData = $response->json();

				Log::info('[BackendCreateLocation] Location Geocoding: Result Status', [
					'location_id' => $location->id,
					'google_status' => $geocodeData['status'],
					'result_count' => count($geocodeData['results'] ?? [])
				]);

				if ($geocodeData['status'] === 'OK' && !empty($geocodeData['results'])) {
					$googleId = $geocodeData['results'][0]['place_id'];

					$location->update(['google_id' => $googleId]);

					Log::info('[BackendCreateLocation] Location Geocoding: Google ID Updated', [
						'location_id' => $location->id,
						'google_id' => $googleId,
						'formatted_address' => $geocodeData['results'][0]['formatted_address'] ?? 'N/A'
					]);
				} else {
					Log::warning('[BackendCreateLocation] Location Geocoding: No Results Found', [
						'location_id' => $location->id,
						'address' => $location->full_address
					]);
				}
			} else {
				Log::error('[BackendCreateLocation] Location Geocoding: API Call Failed', [
					'location_id' => $location->id,
					'status_code' => $response->status(),
					'response_body' => $response->body()
				]);
			}
		} catch (Exception $e) {
			Log::error('[BackendCreateLocation] Location Geocoding: Unexpected Error', [
				'location_id' => $location->id,
				'error_message' => $e->getMessage(),
				'error_trace' => $e->getTraceAsString(),
				'address' => $location->full_address ?? 'Unknown'
			]);
		}

		Log::info('[BackendCreateLocation] About to dispatch CreateStoreFromLocation job', [
			'location_id' => $location->id
		]);

		try {
			CreateStoreFromLocation::dispatch($location->id);
			Log::info('[BackendCreateLocation] Successfully dispatched CreateStoreFromLocation job for location: ' . $location->id);
		} catch (Exception $e) {
			Log::error('[BackendCreateLocation] Failed to dispatch CreateStoreFromLocation job', [
				'location_id' => $location->id,
				'error_message' => $e->getMessage(),
				'error_trace' => $e->getTraceAsString()
			]);
		}
	}
}