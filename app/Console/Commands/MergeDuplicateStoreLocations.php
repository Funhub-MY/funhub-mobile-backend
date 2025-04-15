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
    protected $signature = 'locations:merge-duplicates 
                            {--dry-run : Run without making any changes to the database} 
                            {--store_id= : Process specific store IDs (comma-separated)} 
                            {--store_name= : Process only stores with these names (comma-separated, partial matching)}
                            {--location_id= : Process only specific location IDs (comma-separated)}
                            {--similarity=80 : Minimum name similarity percentage to consider stores as duplicates}';

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
        $storeIds = $this->option('store_id');
        $locationIds = $this->option('location_id');
        $storeNames = $this->option('store_name');
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
        // Get count without loading all articles into memory
        $count = $fromLocation->articles()->count();
        
        $this->info("Found {$count} articles to move from location {$fromLocation->id} to {$toLocation->id}");
        
        if ($count === 0) {
            return 0;
        }
        
        if (!$isDryRun) {
            // Process in chunks to reduce memory usage
            $fromLocation->articles()->chunk(50, function ($articles) use ($toLocation, $fromLocation) {
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
            });
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
        // Get count without loading all ratings into memory
        $count = $fromLocation->ratings()->count();
        
        $this->info("Found {$count} ratings to move from location {$fromLocation->id} to {$toLocation->id}");
        
        if ($count === 0) {
            return 0;
        }
        
        if (!$isDryRun) {
            // Process in chunks to reduce memory usage
            $fromLocation->ratings()->chunk(50, function ($ratings) use ($toLocation, $fromLocation) {
                foreach ($ratings as $rating) {
                    // Check if a rating from the same user already exists in the target location
                    $existingRating = $toLocation->ratings()
                        ->where('user_id', $rating->user_id)
                        ->first();
                    
                    if (!$existingRating) {
                        // Create a new rating in the target location using insert instead of create+save
                        DB::table('location_ratings')->insert([
                            'location_id' => $toLocation->id,
                            'user_id' => $rating->user_id,
                            'rating' => $rating->rating,
                            'created_at' => $rating->created_at,
                            'updated_at' => $rating->updated_at
                        ]);
                        
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
            });
            
            // Update the average rating of the target location using direct query for efficiency
            $avgRating = DB::table('location_ratings')
                ->where('location_id', $toLocation->id)
                ->avg('rating') ?? 0;
                
            DB::table('locations')
                ->where('id', $toLocation->id)
                ->update(['average_ratings' => $avgRating]);
            
            $this->info("Updated average rating for location (ID: {$toLocation->id}) to {$avgRating}");
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
