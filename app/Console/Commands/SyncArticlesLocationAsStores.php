<?php

namespace App\Console\Commands;

use App\Models\Store;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class SyncArticlesLocationAsStores extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'articles:sync-location-as-stores';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        // get all location with Article and doesnt currently linked to a Store
        $locations = \App\Models\Location::whereHas('articles', function ($query) {
            $query->where('articles.status', \App\Models\Article::STATUS_PUBLISHED);
        })
        ->whereNotNull('google_id')
        ->doesntHave('stores')
        ->get();

        $this->info('Total locations with articles that does not have stores: ' . $locations->count());

        // create a Store for each Location
        foreach($locations as $location)
        {
              // create store
            $store = Store::create([
                'user_id' => null,
                'name' => $location->name,
                'manager_name' => null,
                'business_phone_no' => null,
                'business_hours' => null,
                'address' => $location->full_address,
                'address_postcode' => $location->zip_code,
                'lang' => $location->lat,
                'long' => $location->lng,
                'is_hq' => false,
                'state_id' => $location->state_id,
                'country_id' => $location->country_id,
            ]);

            // also attach the location to the store
            $store->location()->attach($location->id);

            $this->info('Store created for location: ' . $location->id . ' with store id: ' . $store->id);
            Log::info('Store created for location: ' . $location->id . ' with store id: ' . $store->id);
        }

        // rerun scout import for Store model
        $this->call('scout:import', ['model' => 'App\Models\Store']);

        return Command::SUCCESS;
    }
}
