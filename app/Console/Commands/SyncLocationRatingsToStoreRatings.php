<?php

namespace App\Console\Commands;

use App\Models\Location;
use App\Models\LocationRating;
use App\Models\Store;
use App\Models\StoreRating;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Sync Location Ratings to Store Ratings
 * ADHOC RUN
 */
class SyncLocationRatingsToStoreRatings extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'locations:sync-ratings-to-store-ratings {--location_ids=} {--store_ids=} {--force} {--dry-run : Run without making actual changes}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sync location ratings to store ratings for locations that have stores attached';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $locationIds = $this->option('location_ids');
        $storeIds = $this->option('store_ids');
        $force = $this->option('force');
        $dryRun = $this->option('dry-run');

        $this->info('Starting to ' . ($dryRun ? 'simulate' : 'sync') . ' location ratings to store ratings...');

        try {
            if ($locationIds) {
                // Process specific locations
                $locationIds = explode(',', $locationIds);
                $this->processLocations($locationIds, $force, $dryRun);
            } elseif ($storeIds) {
                // Process specific stores
                $storeIds = explode(',', $storeIds);
                $this->processStores($storeIds, $force, $dryRun);
            } else {
                // Process stores with missing location ratings (optimized approach)
                $this->processStoresWithMissingRatings($force, $dryRun);
            }

            $this->info(($dryRun ? 'Dry run' : 'Sync') . ' completed successfully!');
            return Command::SUCCESS;
        } catch (\Exception $e) {
            $this->error('An error occurred: ' . $e->getMessage());
            Log::error('Error in SyncLocationRatingsToStoreRatings command', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return Command::FAILURE;
        }
    }

    /**
     * Process specific locations by ID
     *
     * @param array $locationIds
     * @param bool $force
     * @param bool $dryRun
     * @return void
     */
    private function processLocations(array $locationIds, bool $force, bool $dryRun)
    {
        $locations = Location::whereIn('id', $locationIds)
            ->whereHas('ratings')
            ->whereHas('stores')
            ->orderBy('id', 'desc')
            ->get();

        $this->info("Found {$locations->count()} locations with ratings and stores attached");

        foreach ($locations as $location) {
            $this->syncRatingsForLocation($location, $force, $dryRun);
        }
    }

    /**
     * Process specific stores by ID
     *
     * @param array $storeIds
     * @param bool $force
     * @param bool $dryRun
     * @return void
     */
    private function processStores(array $storeIds, bool $force, bool $dryRun)
    {
        $stores = Store::whereIn('id', $storeIds)
            ->whereHas('location', function ($query) {
                $query->whereHas('ratings');
            })
            ->orderBy('id', 'desc')
            ->get();

        $this->info("Found {$stores->count()} stores with locations that have ratings");

        foreach ($stores as $store) {
            $locations = $store->location;
            foreach ($locations as $location) {
                if ($location->ratings->count() > 0) {
                    $this->syncRatingsForLocationAndStore($location, $store, $force, $dryRun);
                }
            }
        }
    }

    /**
     * Process all locations with ratings that have stores attached
     * 
     * @param bool $force
     * @param bool $dryRun
     * @return void
     */
    private function processAllLocations(bool $force, bool $dryRun)
    {
        // Find all locations that have ratings and are connected to stores
        $locations = Location::whereHas('ratings')
            ->whereHas('stores')
            ->orderBy('id', 'desc')
            ->get();

        $this->info("Found {$locations->count()} locations with ratings and stores attached");

        foreach ($locations as $location) {
            $this->syncRatingsForLocation($location, $force, $dryRun);
        }
    }
    
    /**
     * Process stores with missing location ratings (optimized approach with chunking)
     * 
     * @param bool $force
     * @param bool $dryRun
     * @return void
     */
    private function processStoresWithMissingRatings(bool $force, bool $dryRun)
    {
        $this->info("Finding stores with missing location ratings...");
        
        // Count total stores to process
        $totalStores = DB::table('stores')
            ->join('locatables', function ($join) {
                $join->on('locatables.locatable_id', '=', 'stores.id')
                    ->where('locatables.locatable_type', '=', Store::class);
            })
            ->join('locations', 'locations.id', '=', 'locatables.location_id')
            ->join('location_ratings', 'location_ratings.location_id', '=', 'locations.id')
            ->groupBy('stores.id')
            ->count(DB::raw('distinct stores.id'));
            
        $this->info("Found {$totalStores} stores with location ratings to process");
        
        $processedCount = 0;
        $chunkSize = 50; // Process 50 stores at a time
        $currentChunk = 0;
        
        // Process in chunks to reduce memory usage
        while (true) {
            // Get a chunk of store IDs
            $storeIds = DB::table('stores')
                ->select('stores.id as store_id')
                ->join('locatables', function ($join) {
                    $join->on('locatables.locatable_id', '=', 'stores.id')
                        ->where('locatables.locatable_type', '=', Store::class);
                })
                ->join('locations', 'locations.id', '=', 'locatables.location_id')
                ->join('location_ratings', 'location_ratings.location_id', '=', 'locations.id')
                ->groupBy('stores.id')
                ->orderBy('stores.id', 'desc')
                ->skip($currentChunk * $chunkSize)
                ->take($chunkSize)
                ->pluck('store_id')
                ->toArray();
            
            // If no more stores to process, break the loop
            if (empty($storeIds)) {
                break;
            }
            
            $this->info("Processing chunk " . ($currentChunk + 1) . " with " . count($storeIds) . " stores");
            
            foreach ($storeIds as $storeId) {
                // Get the store with eager loading to reduce queries
                $store = Store::with(['location.ratings', 'storeRatings'])->find($storeId);
                
                // Make sure we have a valid Store instance
                if (!$store instanceof Store) {
                    continue;
                }
                
                if (!$store || $store->location->isEmpty()) {
                    continue;
                }
                
                $this->info("Processing store ID: {$store->id} with {$store->location->count()} locations");
                
                foreach ($store->location as $location) {
                    // Get location ratings that don't exist in store ratings
                    $locationRatings = $this->findMissingLocationRatings($location, $store, $force);
                    
                    if ($locationRatings->count() > 0) {
                        $this->info("Found {$locationRatings->count()} missing ratings for location ID: {$location->id}");
                        $this->syncSpecificRatings($location, $store, $locationRatings, $dryRun);
                        $processedCount++;
                    }
                    
                    // Clear location ratings to free memory
                    $locationRatings = null;
                }
                
                // Clear references to free memory
                $store = null;
            }
            
            // Move to next chunk
            $currentChunk++;
            
            // Force garbage collection after each chunk
            if (function_exists('gc_collect_cycles')) {
                gc_collect_cycles();
            }
            
            $this->info("Completed chunk " . $currentChunk . ", processed {$processedCount} stores with missing ratings so far");
        }
        
        $this->info("Completed processing {$processedCount} stores with missing ratings");
    }
    
    /**
     * Find location ratings that don't exist in store ratings
     *
     * @param Location $location
     * @param Store $store
     * @param bool $force
     * @return \Illuminate\Support\Collection
     */
    private function findMissingLocationRatings(Location $location, Store $store, bool $force)
    {
        // If no ratings, return empty collection
        if ($location->ratings->isEmpty()) {
            return collect([]);
        }
        
        // If force is true, return all location ratings
        if ($force) {
            return $location->ratings;
        }
        
        // Get all user IDs who have rated this location
        $locationRatingUserIds = $location->ratings->pluck('user_id')->toArray();
        
        // Get all user IDs who have already rated this store
        $existingStoreRatingUserIds = $store->storeRatings->pluck('user_id')->toArray();
        
        // Find missing user IDs efficiently
        $missingUserIds = array_diff($locationRatingUserIds, $existingStoreRatingUserIds);
        
        if (empty($missingUserIds)) {
            return collect([]);
        }
        
        // Return only the ratings we need to process
        $result = $location->ratings->whereIn('user_id', $missingUserIds);
        
        // Clear arrays to free memory
        unset($locationRatingUserIds, $existingStoreRatingUserIds, $missingUserIds);
        
        return $result;
    }
    
    /**
     * Sync specific ratings from location to store
     *
     * @param Location $location
     * @param Store $store
     * @param \Illuminate\Support\Collection $locationRatings
     * @param bool $dryRun
     * @return void
     */
    private function syncSpecificRatings(Location $location, Store $store, $locationRatings, bool $dryRun)
    {
        $syncedCount = 0;
        $batchSize = 100; // Process ratings in smaller batches
        $ratingsArray = $locationRatings->toArray();
        $totalRatings = count($ratingsArray);
        
        // Process in smaller batches to reduce memory usage
        for ($i = 0; $i < $totalRatings; $i += $batchSize) {
            $batch = array_slice($ratingsArray, $i, $batchSize);
            
            foreach ($batch as $locationRating) {
                if (!$dryRun) {
                    // Use DB::table instead of Eloquent to avoid triggering Scout
                    // Format dates properly for MySQL timestamp format
                    $createdAt = $locationRating['created_at'] instanceof \DateTime 
                        ? $locationRating['created_at']->format('Y-m-d H:i:s')
                        : date('Y-m-d H:i:s', strtotime($locationRating['created_at']));
                    
                    $updatedAt = $locationRating['updated_at'] instanceof \DateTime 
                        ? $locationRating['updated_at']->format('Y-m-d H:i:s')
                        : date('Y-m-d H:i:s', strtotime($locationRating['updated_at']));
                    
                    DB::table('store_ratings')->insert([
                        'store_id' => $store->id,
                        'user_id' => $locationRating['user_id'],
                        'rating' => $locationRating['rating'],
                        'created_at' => $createdAt,
                        'updated_at' => $updatedAt
                    ]);
                }
                
                $this->line("- " . ($dryRun ? '[DRY RUN] Would create' : 'Created') . ": Store rating for store ID: {$store->id}, user ID: {$locationRating['user_id']}");
                $syncedCount++;
            }
            
            // Clear batch to free memory
            unset($batch);
            
            // Force garbage collection after each batch
            if (function_exists('gc_collect_cycles')) {
                gc_collect_cycles();
            }
        }
        
        // Clear ratings array to free memory
        unset($ratingsArray);
        
        if ($syncedCount > 0) {
            // Update store's average rating
            $this->updateStoreAverageRating($store, $dryRun);
            $this->info("Synced {$syncedCount} ratings from location ID: {$location->id} to store ID: {$store->id}");
        }
    }

    /**
     * Sync ratings for a specific location to all its attached stores
     *
     * @param Location $location
     * @param bool $force
     * @param bool $dryRun
     * @return void
     */
    private function syncRatingsForLocation(Location $location, bool $force, bool $dryRun)
    {
        $stores = $location->stores;
        $this->info("Processing location ID: {$location->id} with {$stores->count()} stores attached");

        foreach ($stores as $store) {
            $this->syncRatingsForLocationAndStore($location, $store, $force, $dryRun);
        }
    }

    /**
     * Sync ratings from a location to a specific store
     *
     * @param Location $location
     * @param Store $store
     * @param bool $force
     * @param bool $dryRun
     * @return void
     */
    private function syncRatingsForLocationAndStore(Location $location, Store $store, bool $force, bool $dryRun)
    {
        $locationRatings = $location->ratings;
        $this->info("Processing store ID: {$store->id} with {$locationRatings->count()} ratings from location ID: {$location->id}");

        $syncedCount = 0;
        $skippedCount = 0;

        foreach ($locationRatings as $locationRating) {
            // Check if store rating already exists for this user
            // Use DB query instead of Eloquent to avoid triggering Scout
            $existingRating = DB::table('store_ratings')
                ->where('store_id', $store->id)
                ->where('user_id', $locationRating->user_id)
                ->first();

            if ($existingRating && !$force) {
                $this->line("- Skipped: Store rating already exists for store ID: {$store->id}, user ID: {$locationRating->user_id}");
                $skippedCount++;
                continue;
            }

            // Create or update store rating
            if ($existingRating) {
                if (!$dryRun) {
                    // Use DB::table instead of Eloquent to avoid triggering Scout
                    // Format date properly for MySQL timestamp format
                    $updatedAt = $locationRating->updated_at instanceof \DateTime 
                        ? $locationRating->updated_at->format('Y-m-d H:i:s')
                        : date('Y-m-d H:i:s', strtotime($locationRating->updated_at));
                    
                    DB::table('store_ratings')
                        ->where('id', $existingRating->id)
                        ->update([
                            'rating' => $locationRating->rating,
                            'updated_at' => $updatedAt
                        ]);
                }
                $this->line("- " . ($dryRun ? '[DRY RUN] Would update' : 'Updated') . ": Store rating for store ID: {$store->id}, user ID: {$locationRating->user_id}");
            } else {
                if (!$dryRun) {
                    // Use DB::table instead of Eloquent to avoid triggering Scout
                    // Format dates properly for MySQL timestamp format
                    $createdAt = $locationRating->created_at instanceof \DateTime 
                        ? $locationRating->created_at->format('Y-m-d H:i:s')
                        : date('Y-m-d H:i:s', strtotime($locationRating->created_at));
                    
                    $updatedAt = $locationRating->updated_at instanceof \DateTime 
                        ? $locationRating->updated_at->format('Y-m-d H:i:s')
                        : date('Y-m-d H:i:s', strtotime($locationRating->updated_at));
                    
                    DB::table('store_ratings')->insert([
                        'store_id' => $store->id,
                        'user_id' => $locationRating->user_id,
                        'rating' => $locationRating->rating,
                        'created_at' => $createdAt,
                        'updated_at' => $updatedAt
                    ]);
                }
                $this->line("- " . ($dryRun ? '[DRY RUN] Would create' : 'Created') . ": Store rating for store ID: {$store->id}, user ID: {$locationRating->user_id}");
            }

            $syncedCount++;
        }

        // Update store's average rating
        $this->updateStoreAverageRating($store, $dryRun);

        $this->info("Completed store ID: {$store->id} - Synced: {$syncedCount}, Skipped: {$skippedCount}");
    }

    /**
     * Update the store's average rating
     *
     * @param Store $store
     * @param bool $dryRun
     * @return void
     */
    private function updateStoreAverageRating(Store $store, bool $dryRun)
    {
        $averageRating = DB::table('store_ratings')->where('store_id', $store->id)->avg('rating') ?? 0;
        
        if (!$dryRun) {
            // Use DB::table instead of Eloquent to avoid triggering Scout
            DB::table('stores')
                ->where('id', $store->id)
                ->update([
                    'ratings' => $averageRating
                ]);
            
            // Dispatch job to update store search index
            dispatch(new \App\Jobs\IndexStore($store->id));
            
            Log::info('Updated store average rating', [
                'store_id' => $store->id,
                'average_rating' => $averageRating
            ]);
        }
        
        $this->line("- " . ($dryRun ? '[DRY RUN] Would update' : 'Updated') . " store average rating to: {$averageRating}");


    }
}
