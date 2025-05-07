<?php

namespace App\Console\Commands;

use App\Models\Location;
use App\Models\LocationRating;
use App\Models\Store;
use App\Models\StoreRating;
use App\Models\Article;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Collection;

class MergeDuplicateStoreLocations extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'locations:merge-duplicates {--dry-run} {--store-id=} {--store-name=} {--location-id=} {--similarity=80}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Find and merge duplicate store locations, keeping the ones with onboarded stores (stores with user_id)';

    /**
     * Calculate similarity between two strings with support for multi-byte characters (including Chinese)
     * 
     * @param string $str1
     * @param string $str2
     * @return float Similarity percentage (0-100)
     */
    private function calculateStringSimilarity(string $str1, string $str2): float
    {
        // Normalize and clean strings
        $str1 = mb_strtolower(trim($str1), 'UTF-8');
        $str2 = mb_strtolower(trim($str2), 'UTF-8');
        
        if (empty($str1) && empty($str2)) {
            return 100.0;
        }
        
        if (empty($str1) || empty($str2)) {
            return 0.0;
        }
        
        // For multi-byte strings (like Chinese), use similar_text which works better than levenshtein
        // for non-Latin character sets
        $similarPercent = 0;
        similar_text($str1, $str2, $similarPercent);
        
        // If strings contain multi-byte characters, use similar_text result
        if (mb_strlen($str1, 'UTF-8') !== strlen($str1) || mb_strlen($str2, 'UTF-8') !== strlen($str2)) {
            return $similarPercent;
        }
        
        // For ASCII strings, levenshtein often gives better results
        $levenshtein = levenshtein($str1, $str2);
        $maxLength = max(mb_strlen($str1, 'UTF-8'), mb_strlen($str2, 'UTF-8'));
        
        return (1 - ($levenshtein / $maxLength)) * 100;
    }
    
    /**
     * Check if coordinates are equal (exact match with no margin of error)
     * 
     * @param float $lat1
     * @param float $lng1
     * @param float $lat2
     * @param float $lng2
     * @return bool
     */
    private function coordinatesMatch(float $lat1, float $lng1, float $lat2, float $lng2): bool
    {
        // Exact match with no margin of error
        return ($lat1 === $lat2 && $lng1 === $lng2);
    }

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $isDryRun = $this->option('dry-run');
        $storeIds = $this->option('store-id');
        $locationIds = $this->option('location-id');
        $storeNames = $this->option('store-name');
        // $ignoreMalls = $this->option('ignore_malls');
        $minSimilarity = (int) $this->option('similarity') ?: 80;

        if ($isDryRun) {
            $this->info('Running in dry-run mode. No changes will be made to the database.');
        }

        $this->info('Starting to find and merge duplicate store locations...');
        Log::info('[MergeDuplicateStoreLocations] Command started', [
            'dry_run' => $isDryRun,
            'store_ids' => $storeIds,
            'location_ids' => $locationIds,
            'store_names' => $storeNames,
            // 'ignore_malls' => $ignoreMalls
        ]);

        // Process specific store IDs if provided
        if ($storeIds) {
            $storeIdArray = array_map('trim', explode(',', $storeIds));
            $this->info('Filtering by store IDs: ' . implode(', ', $storeIdArray));
            
            return $this->processSpecificStores($storeIdArray, $isDryRun);
        }
        
        // Process stores by name if provided
        if ($storeNames) {
            $storeNameArray = array_map('trim', explode(',', $storeNames));
            $this->info('Filtering by store names: ' . implode(', ', $storeNameArray));
            
            return $this->processStoresByName($storeNameArray, $isDryRun);
        }
        
        // Process specific location IDs if provided
        if ($locationIds) {
            $locationIdArray = array_map('trim', explode(',', $locationIds));
            $this->info('Filtering by location IDs: ' . implode(', ', $locationIdArray));
            
            return $this->processSpecificLocations($locationIdArray, $isDryRun);
        }

        try {
            // Step 1: Find locations with more than one Store attached (potential duplicates)
            $this->info('Step 1: Finding locations with more than one Store attached (potential duplicates)...');
            $duplicateLocations = DB::table('locatables')
                ->select('location_id', DB::raw('COUNT(*) as store_count'))
                ->where('locatable_type', Store::class)
                ->groupBy('location_id')
                ->having('store_count', '>', 1)
                ->pluck('location_id');

            $this->info('Found ' . count($duplicateLocations) . ' locations with >1 store attached.');
            Log::info('[MergeDuplicateStoreLocations] Locations with >1 store attached', [
                'count' => count($duplicateLocations)
            ]);

            $totalProcessed = 0;
            $totalMerged = 0;

            foreach ($duplicateLocations as $locationId) {
                $this->info("Processing location_id: $locationId");
                $stores = DB::table('locatables')
                    ->join('stores', 'locatables.locatable_id', '=', 'stores.id')
                    ->where('locatables.location_id', $locationId)
                    ->where('locatables.locatable_type', Store::class)
                    ->select('stores.*', 'locatables.id as locatable_id')
                    ->get();

                $legitStores = $stores->whereNotNull('user_id');
                $duplicateStores = $stores->whereNull('user_id');

                if ($legitStores->isEmpty()) {
                    $this->warn("No legit (user_id != null) store for location_id $locationId, skipping.");
                    continue;
                }

                // Pick the first legit store as the canonical one
                $canonicalStore = $legitStores->first();
                $canonicalStoreModel = Store::find($canonicalStore->id);
                $locationModel = Location::find($locationId);
                $this->info("Legit store for location_id $locationId: Store ID {$canonicalStore->id}, Name: {$canonicalStore->name}");

                foreach ($duplicateStores as $dupStore) {
                    $dupStoreModel = Store::find($dupStore->id);
                    if (!$dupStoreModel) {
                        $this->warn("Duplicate store ID {$dupStore->id} not found, skipping.");
                        continue;
                    }
                    $this->info("Handling duplicate store ID {$dupStore->id} (Name: {$dupStore->name}) for location_id $locationId");

                    // Move articles from duplicate store/location to legit store/location
                    $articlesMoved = $this->moveArticles($locationModel, $locationModel, $isDryRun); // If articles are tied to location, not store
                    // If articles are tied to store, you'd want to reassign them here

                    // Move ratings from duplicate location to legit location (if ratings are store-based, adjust logic)
                    $ratingsMoved = $this->moveLocationRatings($locationModel, $locationModel, $isDryRun);

                    // Update ratings for legit store
                    $this->updateStoreRatings($canonicalStoreModel, $isDryRun);

                    // Delete the duplicate store and its locatable link
                    if (!$isDryRun) {
                        DB::table('locatables')
                            ->where('locatable_id', $dupStore->id)
                            ->where('locatable_type', Store::class)
                            ->where('location_id', $locationId)
                            ->delete();
                        $dupStoreModel->delete();
                        $this->info("Deleted duplicate store ID {$dupStore->id}");
                        Log::info('[MergeDuplicateStoreLocations] Deleted duplicate store', [
                            'store_id' => $dupStore->id,
                            'location_id' => $locationId
                        ]);
                    } else {
                        $this->info("[DRY RUN] Would delete duplicate store ID {$dupStore->id}");
                    }
                    $totalMerged++;
                }
                $totalProcessed++;
                $this->info("Completed processing for location_id $locationId");
            }

            $this->info("Command completed. Processed {$totalProcessed} locations, merged {$totalMerged} duplicate stores.");
            Log::info('[MergeDuplicateStoreLocations] Command completed', [
                'processed_locations' => $totalProcessed,
                'merged_stores' => $totalMerged
            ]);
            return Command::SUCCESS;
        } catch (\Exception $e) {
            $this->error("An error occurred: {$e->getMessage()}");
            Log::error('[MergeDuplicateStoreLocations] Error occurred', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            $this->error("Error: {$e->getMessage()}");
            return Command::FAILURE;
        }
    }
    
    /**
     * Process specific stores by ID
     * 
     * @param array $storeIds
     * @param bool $isDryRun
     * @return int
     */
    private function processSpecificStores(array $storeIds, bool $isDryRun): int
    {
        $this->info('Processing specific stores by ID...');
        $totalProcessed = 0;
        $totalMerged = 0;
        
        // Get the stores by ID
        $stores = Store::whereIn('id', $storeIds)->get();
        
        if ($stores->isEmpty()) {
            $this->warn('No stores found with the provided IDs.');
            return Command::SUCCESS;
        }
        
        $this->info('Found ' . $stores->count() . ' stores to process.');
        
        foreach ($stores as $store) {
            $this->info("Processing store ID: {$store->id}, Name: {$store->name}");
            
            // Find the location for this store
            $location = DB::table('locatables')
                ->where('locatable_id', $store->id)
                ->where('locatable_type', Store::class)
                ->first();
                
            if (!$location) {
                $this->warn("No location found for store ID {$store->id}, skipping.");
                continue;
            }
            
            // Find other stores at the same location
            $otherStores = DB::table('locatables')
                ->join('stores', 'locatables.locatable_id', '=', 'stores.id')
                ->where('locatables.location_id', $location->location_id)
                ->where('locatables.locatable_type', Store::class)
                ->where('stores.id', '!=', $store->id)
                ->select('stores.*', 'locatables.id as locatable_id')
                ->get();
                
            if ($otherStores->isEmpty()) {
                $this->info("No duplicate stores found for store ID {$store->id} at location ID {$location->location_id}.");
                continue;
            }
            
            $this->info("Found " . $otherStores->count() . " potential duplicate stores at location ID {$location->location_id}.");
            
            // Determine which store should be kept (prefer the one with user_id)
            $canonicalStore = $store;
            $duplicateStores = $otherStores;
            
            if ($store->user_id === null && $otherStores->whereNotNull('user_id')->isNotEmpty()) {
                $canonicalStore = $otherStores->whereNotNull('user_id')->first();
                $duplicateStores = collect([$store])->merge($otherStores->where('id', '!=', $canonicalStore->id));
                $this->info("Store ID {$store->id} has no user_id, using store ID {$canonicalStore->id} as canonical.");
            } else {
                $this->info("Using store ID {$store->id} as canonical.");
            }
            
            $canonicalStoreModel = Store::find($canonicalStore->id);
            $locationModel = Location::find($location->location_id);
            
            foreach ($duplicateStores as $dupStore) {
                $dupStoreModel = Store::find($dupStore->id);
                if (!$dupStoreModel) {
                    $this->warn("Duplicate store ID {$dupStore->id} not found, skipping.");
                    continue;
                }
                $this->info("Handling duplicate store ID {$dupStore->id} (Name: {$dupStore->name}) for location_id {$location->location_id}");
                
                // Move articles from duplicate store/location to legit store/location
                $articlesMoved = $this->moveArticles($locationModel, $locationModel, $isDryRun);
                
                // Move ratings from duplicate location to legit location
                $ratingsMoved = $this->moveLocationRatings($locationModel, $locationModel, $isDryRun);
                
                // Update ratings for legit store
                $this->updateStoreRatings($canonicalStoreModel, $isDryRun);
                
                // Delete the duplicate store and its locatable link
                if (!$isDryRun) {
                    DB::table('locatables')
                        ->where('locatable_id', $dupStore->id)
                        ->where('locatable_type', Store::class)
                        ->where('location_id', $location->location_id)
                        ->delete();
                    $dupStoreModel->delete();
                    $this->info("Deleted duplicate store ID {$dupStore->id}");
                    Log::info('[MergeDuplicateStoreLocations] Deleted duplicate store', [
                        'store_id' => $dupStore->id,
                        'location_id' => $location->location_id
                    ]);
                } else {
                    $this->info("[DRY RUN] Would delete duplicate store ID {$dupStore->id}");
                }
                $totalMerged++;
            }
            $totalProcessed++;
        }
        
        $this->info("Command completed. Processed {$totalProcessed} stores, merged {$totalMerged} duplicate stores.");
        Log::info('[MergeDuplicateStoreLocations] Command completed', [
            'processed_stores' => $totalProcessed,
            'merged_stores' => $totalMerged
        ]);
        
        return Command::SUCCESS;
    }
    
    /**
     * Process stores by name
     * 
     * @param array $storeNames
     * @param bool $isDryRun
     * @return int
     */
    private function processStoresByName(array $storeNames, bool $isDryRun): int
    {
        $this->info('Processing stores by name...');
        $totalProcessed = 0;
        $totalMerged = 0;
        
        foreach ($storeNames as $storeName) {
            $this->info("Looking for stores with name like '{$storeName}'...");
            
            // Find stores with similar names
            $stores = Store::where('name', 'like', "%{$storeName}%")->get();
            
            if ($stores->isEmpty()) {
                $this->warn("No stores found with name like '{$storeName}'.");
                continue;
            }
            
            $this->info("Found " . $stores->count() . " stores with name like '{$storeName}'.");
            
            // Group stores by location
            $storesByLocation = [];
            
            foreach ($stores as $store) {
                $locations = DB::table('locatables')
                    ->where('locatable_id', $store->id)
                    ->where('locatable_type', Store::class)
                    ->pluck('location_id');
                    
                foreach ($locations as $locationId) {
                    if (!isset($storesByLocation[$locationId])) {
                        $storesByLocation[$locationId] = [];
                    }
                    $storesByLocation[$locationId][] = $store;
                }
            }
            
            // Process each location with multiple stores
            foreach ($storesByLocation as $locationId => $locationStores) {
                if (count($locationStores) <= 1) {
                    continue; // Skip locations with only one store
                }
                
                $this->info("Processing location_id: {$locationId} with " . count($locationStores) . " stores.");
                
                // Determine which store should be kept (prefer the one with user_id)
                $storesCollection = collect($locationStores);
                $legitStores = $storesCollection->whereNotNull('user_id');
                
                if ($legitStores->isEmpty()) {
                    $this->warn("No legit (user_id != null) store for location_id {$locationId}, skipping.");
                    continue;
                }
                
                $canonicalStore = $legitStores->first();
                $duplicateStores = $storesCollection->where('id', '!=', $canonicalStore->id);
                
                $this->info("Legit store for location_id {$locationId}: Store ID {$canonicalStore->id}, Name: {$canonicalStore->name}");
                
                $locationModel = Location::find($locationId);
                
                foreach ($duplicateStores as $dupStore) {
                    $this->info("Handling duplicate store ID {$dupStore->id} (Name: {$dupStore->name}) for location_id {$locationId}");
                    
                    // Move articles from duplicate store/location to legit store/location
                    $articlesMoved = $this->moveArticles($locationModel, $locationModel, $isDryRun);
                    
                    // Move ratings from duplicate location to legit location
                    $ratingsMoved = $this->moveLocationRatings($locationModel, $locationModel, $isDryRun);
                    
                    // Update ratings for legit store
                    $this->updateStoreRatings($canonicalStore, $isDryRun);
                    
                    // Delete the duplicate store and its locatable link
                    if (!$isDryRun) {
                        DB::table('locatables')
                            ->where('locatable_id', $dupStore->id)
                            ->where('locatable_type', Store::class)
                            ->where('location_id', $locationId)
                            ->delete();
                        Store::destroy($dupStore->id);
                        $this->info("Deleted duplicate store ID {$dupStore->id}");
                        Log::info('[MergeDuplicateStoreLocations] Deleted duplicate store', [
                            'store_id' => $dupStore->id,
                            'location_id' => $locationId
                        ]);
                    } else {
                        $this->info("[DRY RUN] Would delete duplicate store ID {$dupStore->id}");
                    }
                    $totalMerged++;
                }
                $totalProcessed++;
            }
        }
        
        $this->info("Command completed. Processed {$totalProcessed} locations, merged {$totalMerged} duplicate stores.");
        Log::info('[MergeDuplicateStoreLocations] Command completed', [
            'processed_locations' => $totalProcessed,
            'merged_stores' => $totalMerged
        ]);
        
        return Command::SUCCESS;
    }
    
    /**
     * Process specific locations by ID
     * 
     * @param array $locationIds
     * @param bool $isDryRun
     * @return int
     */
    private function processSpecificLocations(array $locationIds, bool $isDryRun): int
    {
        $this->info('Processing specific locations by ID...');
        $totalProcessed = 0;
        $totalMerged = 0;
        
        foreach ($locationIds as $locationId) {
            $this->info("Processing location_id: {$locationId}");
            
            $stores = DB::table('locatables')
                ->join('stores', 'locatables.locatable_id', '=', 'stores.id')
                ->where('locatables.location_id', $locationId)
                ->where('locatables.locatable_type', Store::class)
                ->select('stores.*', 'locatables.id as locatable_id')
                ->get();
                
            if ($stores->count() <= 1) {
                $this->info("Location ID {$locationId} has " . $stores->count() . " store(s), no duplicates to merge.");
                continue;
            }
            
            $legitStores = $stores->whereNotNull('user_id');
            $duplicateStores = $stores->whereNull('user_id');
            
            if ($legitStores->isEmpty()) {
                $this->warn("No legit (user_id != null) store for location_id {$locationId}, skipping.");
                continue;
            }
            
            // Pick the first legit store as the canonical one
            $canonicalStore = $legitStores->first();
            $canonicalStoreModel = Store::find($canonicalStore->id);
            $locationModel = Location::find($locationId);
            
            $this->info("Legit store for location_id {$locationId}: Store ID {$canonicalStore->id}, Name: {$canonicalStore->name}");
            
            foreach ($duplicateStores as $dupStore) {
                $dupStoreModel = Store::find($dupStore->id);
                if (!$dupStoreModel) {
                    $this->warn("Duplicate store ID {$dupStore->id} not found, skipping.");
                    continue;
                }
                $this->info("Handling duplicate store ID {$dupStore->id} (Name: {$dupStore->name}) for location_id {$locationId}");
                
                // Move articles from duplicate store/location to legit store/location
                $articlesMoved = $this->moveArticles($locationModel, $locationModel, $isDryRun);
                
                // Move ratings from duplicate location to legit location
                $ratingsMoved = $this->moveLocationRatings($locationModel, $locationModel, $isDryRun);
                
                // Update ratings for legit store
                $this->updateStoreRatings($canonicalStoreModel, $isDryRun);
                
                // Delete the duplicate store and its locatable link
                if (!$isDryRun) {
                    DB::table('locatables')
                        ->where('locatable_id', $dupStore->id)
                        ->where('locatable_type', Store::class)
                        ->where('location_id', $locationId)
                        ->delete();
                    $dupStoreModel->delete();
                    $this->info("Deleted duplicate store ID {$dupStore->id}");
                    Log::info('[MergeDuplicateStoreLocations] Deleted duplicate store', [
                        'store_id' => $dupStore->id,
                        'location_id' => $locationId
                    ]);
                } else {
                    $this->info("[DRY RUN] Would delete duplicate store ID {$dupStore->id}");
                }
                $totalMerged++;
            }
            $totalProcessed++;
        }
        
        $this->info("Command completed. Processed {$totalProcessed} locations, merged {$totalMerged} duplicate stores.");
        Log::info('[MergeDuplicateStoreLocations] Command completed', [
            'processed_locations' => $totalProcessed,
            'merged_stores' => $totalMerged
        ]);
        
        return Command::SUCCESS;
    }

    /**
     * Move articles from one location to another
     * 
     * @param Location $fromLocation
     * @param Location $toLocation
     * @param bool $isDryRun
     * @return int Number of articles moved
     */
    private function moveArticles(Location $fromLocation, Location $toLocation, bool $isDryRun): int
    {
        // Find articles associated with the source location
        $articles = Article::whereHas('locations', function ($query) use ($fromLocation) {
            $query->where('locations.id', $fromLocation->id);
        })->get();
        
        if ($articles->isEmpty()) {
            $this->info("No articles found for location ID {$fromLocation->id}.");
            return 0;
        }
        
        $count = 0;
        foreach ($articles as $article) {
            // Check if the article is already associated with the target location
            $alreadyAssociated = $article->locations()->where('locations.id', $toLocation->id)->exists();
            
            if ($alreadyAssociated) {
                $this->info("Article ID {$article->id} is already associated with location ID {$toLocation->id}.");
                continue;
            }
            
            if (!$isDryRun) {
                // Associate the article with the target location
                $article->locations()->attach($toLocation->id);
                $this->info("Moved article ID {$article->id} from location ID {$fromLocation->id} to location ID {$toLocation->id}.");
                Log::info('[MergeDuplicateStoreLocations] Moved article', [
                    'article_id' => $article->id,
                    'from_location_id' => $fromLocation->id,
                    'to_location_id' => $toLocation->id
                ]);
            } else {
                $this->info("[DRY RUN] Would move article ID {$article->id} from location ID {$fromLocation->id} to location ID {$toLocation->id}.");
            }
            $count++;
        }
        
        return $count;
    }

    /**
     * Move location ratings from one location to another
     * 
     * @param Location $fromLocation
     * @param Location $toLocation
     * @param bool $isDryRun
     * @return int Number of ratings moved
     */
    private function moveLocationRatings(Location $fromLocation, Location $toLocation, bool $isDryRun): int
    {
        // Find ratings associated with the source location
        $ratings = LocationRating::where('location_id', $fromLocation->id)->get();
        
        if ($ratings->isEmpty()) {
            $this->info("No ratings found for location ID {$fromLocation->id}.");
            return 0;
        }
        
        $count = 0;
        foreach ($ratings as $rating) {
            // Check if a rating from the same user already exists for the target location
            $existingRating = LocationRating::where('location_id', $toLocation->id)
                ->where('user_id', $rating->user_id)
                ->first();
                
            if ($existingRating) {
                $this->info("Rating from user ID {$rating->user_id} already exists for location ID {$toLocation->id}.");
                continue;
            }
            
            if (!$isDryRun) {
                // Create a new rating for the target location
                LocationRating::create([
                    'user_id' => $rating->user_id,
                    'location_id' => $toLocation->id,
                    'rating' => $rating->rating,
                    'review' => $rating->review,
                    'created_at' => $rating->created_at,
                    'updated_at' => now()
                ]);
                $this->info("Moved rating ID {$rating->id} from location ID {$fromLocation->id} to location ID {$toLocation->id}.");
                Log::info('[MergeDuplicateStoreLocations] Moved location rating', [
                    'rating_id' => $rating->id,
                    'from_location_id' => $fromLocation->id,
                    'to_location_id' => $toLocation->id
                ]);
            } else {
                $this->info("[DRY RUN] Would move rating ID {$rating->id} from location ID {$fromLocation->id} to location ID {$toLocation->id}.");
            }
            $count++;
        }
        
        return $count;
    }

    /**
     * Update store ratings
     * 
     * @param Store $store
     * @param bool $isDryRun
     * @return bool Success status
     */
    private function updateStoreRatings(Store $store, bool $isDryRun): bool
    {
        // Get all locations associated with this store
        $locationIds = DB::table('locatables')
            ->where('locatable_id', $store->id)
            ->where('locatable_type', Store::class)
            ->pluck('location_id');
            
        if ($locationIds->isEmpty()) {
            $this->warn("No locations found for store ID {$store->id}.");
            return false;
        }
        
        // Calculate average rating from all associated locations
        $avgRating = LocationRating::whereIn('location_id', $locationIds)->avg('rating') ?: 0;
        $avgRating = round($avgRating, 2); // Round to 2 decimal places
        
        if (!$isDryRun) {
            
            $store->ratings = $avgRating;
            $store->save();
            
            // Update the search index
            $store->ratings = $avgRating;
            $store->save();
            $this->info("Updated ratings for store ID {$store->id} to {$avgRating}");
            Log::info('[MergeDuplicateStoreLocations] Updated store ratings', [
                'store_id' => $store->id,
                'new_rating' => $avgRating
            ]);
        } else {
            $this->info("[DRY RUN] Would update store ID {$store->id} ratings to {$avgRating}");
        }
        
        return true;
    }
    
    /**
     * Get potential matches for a location (memory efficient)
     * 
     * @param object $location
     * @param Collection $allLocations
     * @param array $processedLocationIds
     * @return array
     */
    private function getPotentialMatches(object $location, Collection $allLocations, array $processedLocationIds): array
    {
        $potentialMatches = [];
        
        // Process in smaller batches to reduce memory usage
        foreach ($allLocations->chunk(50) as $chunk) {
            foreach ($chunk as $potentialDuplicate) {
                if ($location->id === $potentialDuplicate->id || in_array($potentialDuplicate->id, $processedLocationIds)) {
                    continue; // Skip self or already processed
                }
                
                $potentialMatches[] = $potentialDuplicate;
            }
        }
        
        return $potentialMatches;
    }
}
