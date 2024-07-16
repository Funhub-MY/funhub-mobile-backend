<?php

namespace App\Console\Commands;

use App\Models\Location;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class LocationGoogleIdNullFix extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'location:fix-google-id-null {--limit=}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Update null google_id for locations using Google Geocoding API';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $limit = $this->option('limit');

        $locations = Location::whereNull('google_id')
            ->when($limit, function ($query, $limit) {
                return $query->limit($limit);
            })
            ->get();

        $this->info('Found ' . $locations->count() . ' locations with null google_id.');

        $updatedCount = 0;

        foreach ($locations as $location) {
            $address = $location->full_address;

            $response = Http::get('https://maps.googleapis.com/maps/api/geocode/json', [
                'address' => $address,
                'key' => config('filament-google-maps.key'),
            ]);

            if ($response->successful()) {
                $data = $response->json();

                if ($data['status'] === 'OK' && count($data['results']) > 0) {
                    $result = $data['results'][0];
                    $googleId = $result['place_id'];

                    $location->google_id = $googleId;
                    $location->save();

                    $this->info('Updated google_id for location: ' . $location->id. ' with address: '. $location->full_address);
                    $updatedCount++;
                } else {
                    $this->warn('No results found for location: ' . $location->id. ' with address: '. $location->full_address);
                }
            } else {
                $this->error('Failed to retrieve google_id for location: ' . $location->id);
            }
        }

        $remainingNullCount = Location::whereNull('google_id')->count();

        $this->info('Command completed.');
        $this->info('Successfully updated google_id for ' . $updatedCount . ' locations.');
        $this->info('Remaining locations with null google_id: ' . $remainingNullCount);

        return Command::SUCCESS;
    }
}
