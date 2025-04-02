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

class MergeDuplicateStoreLocations extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'locations:merge-duplicates {--dry-run : Run without making any changes to the database} {--store_id= : Process a specific store ID} {--similarity=80 : Minimum name similarity percentage to consider stores as duplicates}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Find and merge duplicate store locations, keeping the ones with onboarded stores (stores with user_id)';

    /**
     * Calculate similarity between two strings (using Levenshtein distance)
     * 
     * @param string $str1
     * @param string $str2
     * @return float Similarity percentage (0-100)
     */
    private function calculateStringSimilarity(string $str1, string $str2): float
    {
        $str1 = strtolower(trim($str1));
        $str2 = strtolower(trim($str2));
        
        if (empty($str1) && empty($str2)) {
            return 100.0;
        }
        
        if (empty($str1) || empty($str2)) {
            return 0.0;
        }
        
        $levenshtein = levenshtein($str1, $str2);
        $maxLength = max(strlen($str1), strlen($str2));
        
        return (1 - ($levenshtein / $maxLength)) * 100;
    }
    
    /**
     * Check if coordinates are equal (within a small margin of error)
     * 
     * @param float $lat1
     * @param float $lng1
     * @param float $lat2
     * @param float $lng2
     * @return bool
     */
    private function coordinatesMatch(float $lat1, float $lng1, float $lat2, float $lng2): bool
    {
        // Allow for a small margin of error (approximately 10 meters)
        $precision = 0.0001;
        
        return (abs($lat1 - $lat2) < $precision && abs($lng1 - $lng2) < $precision);
    }

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $isDryRun = $this->option('dry-run');
        $specificStoreId = $this->option('store_id');
        $minSimilarity = (int) $this->option('similarity') ?: 80;

        if ($isDryRun) {
            $this->info('Running in dry-run mode. No changes will be made to the database.');
        }

        $this->info('Starting to find and merge duplicate store locations...');
        Log::info('[MergeDuplicateStoreLocations] Command started', [
            'dry_run' => $isDryRun,
            'store_id' => $specificStoreId
        ]);

        try {
            // Step 1: Get all locations with stores
            $this->info('Step 1: Getting all locations with stores...');
            
            // Get all locations with their attached stores
            $locationsWithStores = DB::table('locations')
                ->leftJoin('locatables', function ($join) {
                    $join->on('locations.id', '=', 'locatables.location_id')
                        ->where('locatables.locatable_type', '=', Store::class);
                })
                ->leftJoin('stores', 'locatables.locatable_id', '=', 'stores.id')
                ->select(
                    'locations.id', 
                    'locations.lat', 
                    'locations.lng', 
                    'locations.google_id',
                    'locations.name as location_name',
                    'stores.id as store_id', 
                    'stores.user_id', 
                    'stores.name as store_name'
                );
                
            if ($specificStoreId) {
                $store = Store::find($specificStoreId);
                if (!$store) {
                    $this->error("Store with ID {$specificStoreId} not found.");
                    return Command::FAILURE;
                }
                $locationsWithStores->where('stores.id', $specificStoreId);
            }
            
            $locationsData = $locationsWithStores->get();
            
            $this->info("Found {$locationsData->count()} location records to analyze.");
            Log::info('[MergeDuplicateStoreLocations] Found locations with stores', [
                'count' => $locationsData->count()
            ]);

            // Step 2: Find potential duplicates based on name similarity and matching coordinates
            $this->info("Step 2: Finding potential duplicates based on name similarity (min {$minSimilarity}%) and matching coordinates...");
            
            $locationGroups = [];
            $processedLocationIds = [];
            
            foreach ($locationsData as $location) {
                if (in_array($location->id, $processedLocationIds)) {
                    continue; // Skip already processed locations
                }
                
                // Get the full location object for more details
                $fullLocation = Location::find($location->id);
                if (!$fullLocation) {
                    continue; // Skip if location not found
                }
                
                $group = [$location];
                $processedLocationIds[] = $location->id;
                
                // Find potential duplicates
                foreach ($locationsData as $potentialDuplicate) {
                    if ($location->id === $potentialDuplicate->id || in_array($potentialDuplicate->id, $processedLocationIds)) {
                        continue; // Skip self or already processed
                    }
                    
                    // Check if coordinates match
                    $coordinatesMatch = $this->coordinatesMatch(
                        $location->lat, 
                        $location->lng, 
                        $potentialDuplicate->lat, 
                        $potentialDuplicate->lng
                    );
                    
                    if (!$coordinatesMatch) {
                        continue; // Skip if coordinates don't match
                    }
                    
                    // Check name similarity
                    $nameSimilarity = 0;
                    
                    // Try to compare store names first if available
                    if (!empty($location->store_name) && !empty($potentialDuplicate->store_name)) {
                        $nameSimilarity = $this->calculateStringSimilarity(
                            $location->store_name,
                            $potentialDuplicate->store_name
                        );
                    } 
                    // Fall back to location names if store names aren't available
                    else if (!empty($location->location_name) && !empty($potentialDuplicate->location_name)) {
                        $nameSimilarity = $this->calculateStringSimilarity(
                            $location->location_name,
                            $potentialDuplicate->location_name
                        );
                    }
                    
                    // Check if google_id is different (if both have google_id)
                    $differentGoogleId = false;
                    if (!empty($location->google_id) && !empty($potentialDuplicate->google_id)) {
                        $differentGoogleId = ($location->google_id !== $potentialDuplicate->google_id);
                    }
                    
                    // Add to group if meets criteria: coordinates match AND (name similarity >= threshold OR different google_id)
                    if ($coordinatesMatch && ($nameSimilarity >= $minSimilarity || $differentGoogleId)) {
                        $group[] = $potentialDuplicate;
                        $processedLocationIds[] = $potentialDuplicate->id;
                        
                        $this->info("Found potential duplicate: Location {$potentialDuplicate->id} matches Location {$location->id} "
                            . "(Name similarity: {$nameSimilarity}%, Coordinates match: Yes, Different Google ID: "
                            . ($differentGoogleId ? 'Yes' : 'No') . ")");
                    }
                }
                
                // Only add groups with more than one location (potential duplicates)
                if (count($group) > 1) {
                    $locationGroups[] = $group;
                }
            }
            
            $this->info("Found " . count($locationGroups) . " groups of potentially duplicate locations.");
            Log::info('[MergeDuplicateStoreLocations] Found potential duplicate location groups', [
                'count' => count($locationGroups)
            ]);

            $totalProcessed = 0;
            $totalMerged = 0;

            // Step 2: Process each group of potential duplicate locations
            foreach ($locationGroups as $groupIndex => $locationGroup) {
                $this->info("Processing location group #{$groupIndex} with " . count($locationGroup) . " locations");
                
                // Find the authentic location (with an onboarded store that has user_id)
                $authenticLocation = null;
                $authenticStore = null;
                
                foreach ($locationGroup as $locationData) {
                    if (!$locationData->store_id) {
                        continue; // Skip locations without stores
                    }
                    
                    $store = Store::find($locationData->store_id);
                    
                    if ($store && $store->user_id !== null) {
                        $authenticLocation = Location::find($locationData->id);
                        $authenticStore = $store;
                        $this->info("Found authentic location (ID: {$locationData->id}) with onboarded store (ID: {$store->id}, Name: {$store->name})");
                        break;
                    }
                }
                
                if (!$authenticLocation || !$authenticStore) {
                    $this->warn("No authentic location found in group #{$groupIndex}. Skipping.");
                    continue;
                }
                
                $this->info("Using location (ID: {$authenticLocation->id}) with store (ID: {$authenticStore->id}) as primary");
                
                // Process each duplicate location in the group
                foreach ($locationGroup as $locationData) {
                    if ($locationData->id == $authenticLocation->id) {
                        continue; // Skip the authentic location
                    }
                    
                    $duplicateLocation = Location::find($locationData->id);
                    
                    if (!$duplicateLocation) {
                        $this->warn("Location (ID: {$locationData->id}) not found. Skipping.");
                        continue;
                    }
                    
                    // Step 3: Move articles from duplicate location to authentic location
                    $articlesCount = $this->moveArticles($duplicateLocation, $authenticLocation, $isDryRun);
                    
                    // Step 4: Move location ratings to authentic location
                    $ratingsCount = $this->moveLocationRatings($duplicateLocation, $authenticLocation, $isDryRun);
                    
                    // Step 5: Update store ratings if needed
                    $this->updateStoreRatings($authenticStore, $isDryRun);
                    
                    // Step 6: Delete the duplicate location if not in dry-run mode
                    if (!$isDryRun) {
                        // First detach all relationships
                        DB::table('locatables')
                            ->where('location_id', $duplicateLocation->id)
                            ->delete();
                            
                        // Then delete the location
                        $duplicateLocation->delete();
                        
                        $this->info("Deleted duplicate location (ID: {$duplicateLocation->id})");
                        Log::info('[MergeDuplicateStoreLocations] Deleted duplicate location', [
                            'location_id' => $duplicateLocation->id
                        ]);
                    } else {
                        $this->info("[DRY RUN] Would delete duplicate location (ID: {$duplicateLocation->id})");
                    }
                    
                    $totalMerged++;
                }
                
                $totalProcessed++;
                $this->info("Completed processing for location group #{$groupIndex}");
            }
            
            $this->info("Command completed. Processed {$totalProcessed} location groups, merged {$totalMerged} duplicate locations.");
            Log::info('[MergeDuplicateStoreLocations] Command completed', [
                'processed_groups' => $totalProcessed,
                'merged_locations' => $totalMerged
            ]);
            
            return Command::SUCCESS;
        } catch (\Exception $e) {
            $this->error("An error occurred: {$e->getMessage()}");
            Log::error('[MergeDuplicateStoreLocations] Error occurred', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return Command::FAILURE;
        }
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
        $articles = $fromLocation->articles()->get();
        $count = $articles->count();
        
        $this->info("Found {$count} articles to move from location {$fromLocation->id} to {$toLocation->id}");
        
        if ($count === 0) {
            return 0;
        }
        
        if (!$isDryRun) {
            foreach ($articles as $article) {
                // Check if article is already attached to the target location
                $isAlreadyAttached = DB::table('locatables')
                    ->where('location_id', $toLocation->id)
                    ->where('locatable_type', Article::class)
                    ->where('locatable_id', $article->id)
                    ->exists();
                
                if (!$isAlreadyAttached) {
                    // Attach article to the new location
                    $toLocation->articles()->attach($article->id);
                    
                    $this->info("Moved article (ID: {$article->id}) to location (ID: {$toLocation->id})");
                    Log::info('[MergeDuplicateStoreLocations] Moved article', [
                        'article_id' => $article->id,
                        'from_location_id' => $fromLocation->id,
                        'to_location_id' => $toLocation->id
                    ]);
                } else {
                    $this->info("Article (ID: {$article->id}) is already attached to location (ID: {$toLocation->id})");
                }
            }
        } else {
            $this->info("[DRY RUN] Would move {$count} articles from location {$fromLocation->id} to {$toLocation->id}");
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
        $ratings = $fromLocation->ratings()->get();
        $count = $ratings->count();
        
        $this->info("Found {$count} ratings to move from location {$fromLocation->id} to {$toLocation->id}");
        
        if ($count === 0) {
            return 0;
        }
        
        if (!$isDryRun) {
            foreach ($ratings as $rating) {
                // Check if a rating from the same user already exists in the target location
                $existingRating = $toLocation->ratings()
                    ->where('user_id', $rating->user_id)
                    ->first();
                
                if (!$existingRating) {
                    // Create a new rating in the target location
                    $newRating = new LocationRating([
                        'location_id' => $toLocation->id,
                        'user_id' => $rating->user_id,
                        'rating' => $rating->rating,
                        'created_at' => $rating->created_at,
                        'updated_at' => $rating->updated_at
                    ]);
                    
                    $newRating->save();
                    
                    $this->info("Moved rating (ID: {$rating->id}) to location (ID: {$toLocation->id})");
                    Log::info('[MergeDuplicateStoreLocations] Moved rating', [
                        'rating_id' => $rating->id,
                        'from_location_id' => $fromLocation->id,
                        'to_location_id' => $toLocation->id
                    ]);
                } else {
                    $this->info("Rating from user (ID: {$rating->user_id}) already exists in location (ID: {$toLocation->id})");
                }
                
                // Delete the old rating
                $rating->delete();
            }
            
            // Update the average rating of the target location
            $toLocation->average_ratings = $toLocation->ratings()->avg('rating') ?? 0;
            $toLocation->save();
            
            $this->info("Updated average rating for location (ID: {$toLocation->id}) to {$toLocation->average_ratings}");
        } else {
            $this->info("[DRY RUN] Would move {$count} ratings from location {$fromLocation->id} to {$toLocation->id}");
        }
        
        return $count;
    }

    /**
     * Update store ratings based on the location ratings
     *
     * @param Store $store
     * @param bool $isDryRun
     */
    private function updateStoreRatings(Store $store, bool $isDryRun): void
    {
        if (!$isDryRun) {
            // Recalculate the average rating for the store
            $avgRating = $store->storeRatings()->avg('rating') ?? 0;
            
            $store->ratings = $avgRating;
            $store->save();
            
            // Update the search index
            $store->searchable();
            
            $this->info("Updated ratings for store (ID: {$store->id}) to {$avgRating}");
            Log::info('[MergeDuplicateStoreLocations] Updated store ratings', [
                'store_id' => $store->id,
                'ratings' => $avgRating
            ]);
        } else {
            $this->info("[DRY RUN] Would update ratings for store (ID: {$store->id})");
        }
    }
}
