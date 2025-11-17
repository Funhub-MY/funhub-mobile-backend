<?php

namespace App\Console\Commands;

use App\Models\Article;
use App\Models\LocationRating;
use App\Models\Location;
use App\Models\Store;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class MergeStoreById extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'stores:merge {target_id} {duplicate_id} {--dry-run}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Merge a duplicate store into a target store';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $targetId = $this->argument('target_id');
        $duplicateId = $this->argument('duplicate_id');
        $isDryRun = $this->option('dry-run');

        if ($isDryRun) {
            $this->info('Running in dry-run mode. No changes will be made to the database.');
        }

        $this->info("Merging duplicate store ID {$duplicateId} into target store ID {$targetId}");
        
        // Get the target store (the one we want to keep)
        $targetStore = Store::find($targetId);
        if (!$targetStore) {
            $this->error("Target store with ID {$targetId} not found.");
            return Command::FAILURE;
        }
        $this->info("Found target store: {$targetStore->name}");
        
        // Get the duplicate store (the one we want to merge and delete)
        $duplicateStore = Store::find($duplicateId);
        if (!$duplicateStore) {
            $this->error("Duplicate store with ID {$duplicateId} not found.");
            return Command::FAILURE;
        }
        $this->info("Found duplicate store: {$duplicateStore->name}");
        
        // Find the locations for both stores
        $targetLocation = DB::table('locatables')
            ->where('locatable_id', $targetStore->id)
            ->where('locatable_type', Store::class)
            ->first();
            
        if (!$targetLocation) {
            $this->error("No location found for target store ID {$targetId}.");
            return Command::FAILURE;
        }
        $targetLocationModel = Location::find($targetLocation->location_id);
        $this->info("Found target location ID: {$targetLocation->location_id} at coordinates: {$targetLocationModel->lat}, {$targetLocationModel->lng}");
        
        $duplicateLocation = DB::table('locatables')
            ->where('locatable_id', $duplicateStore->id)
            ->where('locatable_type', Store::class)
            ->first();
            
        if (!$duplicateLocation) {
            $this->error("No location found for duplicate store ID {$duplicateId}.");
            return Command::FAILURE;
        }
        $duplicateLocationModel = Location::find($duplicateLocation->location_id);
        $this->info("Found duplicate location ID: {$duplicateLocation->location_id} at coordinates: {$duplicateLocationModel->lat}, {$duplicateLocationModel->lng}");
        
        // Step 1: Move articles from duplicate location to target location
        $this->info("Step 1: Moving articles from duplicate location to target location...");
        $articlesMoved = $this->moveArticles($duplicateLocationModel, $targetLocationModel, $isDryRun);
        $this->info("Moved {$articlesMoved} articles.");
        
        // Step 2: Move ratings from duplicate location to target location
        $this->info("Step 2: Moving ratings from duplicate location to target location...");
        $ratingsMoved = $this->moveLocationRatings($duplicateLocationModel, $targetLocationModel, $isDryRun);
        $this->info("Moved {$ratingsMoved} ratings.");
        
        // Step 3: Update ratings for target store
        $this->info("Step 3: Updating ratings for target store...");
        $this->updateStoreRatings($targetStore, $targetLocationModel, $isDryRun);
        
        // Step 4: Update search indices
        $this->info("Step 4: Updating search indices...");
        if (!$isDryRun) {
            // Make the target store searchable to update its index
            if (method_exists($targetStore, 'searchable')) {
                $targetStore->searchable();
                $this->info("Updated search index for target store ID {$targetId}");
                Log::info('[MergeStores] Updated search index for target store', [
                    'store_id' => $targetId
                ]);
            } else {
                $this->info("Target store model does not have searchable method, skipping search index update.");
            }
        } else {
            $this->info("[DRY RUN] Would update search index for target store ID {$targetId}");
        }
        
        // Step 5: Delete the duplicate store and its locatable link
        $this->info("Step 5: Deleting the duplicate store and its locatable link...");
        if (!$isDryRun) {
            // Delete the duplicate store's locatable link
            DB::table('locatables')
                ->where('locatable_id', $duplicateStore->id)
                ->where('locatable_type', Store::class)
                ->delete();
            
            // Make the duplicate store unsearchable before deleting it
            if (method_exists($duplicateStore, 'unsearchable')) {
                $duplicateStore->unsearchable();
                $this->info("Removed duplicate store ID {$duplicateId} from search index");
                Log::info('[MergeStores] Removed duplicate store from search index', [
                    'store_id' => $duplicateId
                ]);
            }
            
            // Delete the duplicate store
            $duplicateStore->delete();
            
            $this->info("Deleted duplicate store ID {$duplicateId}");
            Log::info('[MergeStores] Deleted duplicate store', [
                'duplicate_store_id' => $duplicateId,
                'target_store_id' => $targetId
            ]);
            
            // Check if anything else is still attached to the duplicate location
            $remainingAttachments = DB::table('locatables')
                ->where('location_id', $duplicateLocation->location_id)
                ->count();
                
            if ($remainingAttachments == 0) {
                // If nothing is attached to the location, delete it
                $duplicateLocationModel->delete();
                $this->info("Deleted duplicate location ID {$duplicateLocation->location_id} as it has no more attachments");
                Log::info('[MergeStores] Deleted duplicate location', [
                    'location_id' => $duplicateLocation->location_id
                ]);
            } else {
                $this->info("Duplicate location ID {$duplicateLocation->location_id} still has {$remainingAttachments} attachments, not deleting");
            }
        } else {
            $this->info("[DRY RUN] Would delete duplicate store ID {$duplicateId}");
            $this->info("[DRY RUN] Would check if duplicate location ID {$duplicateLocation->location_id} can be deleted");
        }
        
        $this->info("Command completed. Successfully merged store ID {$duplicateId} into store ID {$targetId}.");
        
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
        $articles = Article::whereHas('location', function ($query) use ($fromLocation) {
            $query->where('locations.id', $fromLocation->id);
        })->get();
        
        if ($articles->isEmpty()) {
            $this->info("No articles found for location ID {$fromLocation->id}.");
            return 0;
        }
        
        $count = 0;
        foreach ($articles as $article) {
            // Check if the article is already associated with the target location
            $alreadyAssociated = $article->location()->where('locations.id', $toLocation->id)->exists();
            
            if ($alreadyAssociated) {
                $this->info("Article ID {$article->id} is already associated with location ID {$toLocation->id}.");
                continue;
            }
            
            if (!$isDryRun) {
                // First, detach the article from the old location
                DB::table('locatables')
                    ->where('locatable_id', $article->id)
                    ->where('locatable_type', Article::class)
                    ->where('location_id', $fromLocation->id)
                    ->delete();
                    
                // Then, associate the article with the target location
                $article->location()->attach($toLocation->id);
                
                $this->info("Moved article ID {$article->id} from location ID {$fromLocation->id} to location ID {$toLocation->id}.");
                Log::info('[MergeStores] Moved article', [
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
                    'review' => $rating->review ?? '',
                    'created_at' => $rating->created_at,
                    'updated_at' => now()
                ]);
                $this->info("Moved rating ID {$rating->id} from location ID {$fromLocation->id} to location ID {$toLocation->id}.");
                Log::info('[MergeStores] Moved location rating', [
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
     * @param Location $location
     * @param bool $isDryRun
     * @return bool Success status
     */
    private function updateStoreRatings(Store $store, Location $location, bool $isDryRun): bool
    {
        // Calculate average rating from the location
        $avgRating = LocationRating::where('location_id', $location->id)->avg('rating') ?: 0;
        $avgRating = round($avgRating, 2); // Round to 2 decimal places
        
        if (!$isDryRun) {
            $store->ratings = $avgRating;
            $store->save();
            $this->info("Updated ratings for store ID {$store->id} to {$avgRating}.");
            Log::info('[MergeStores] Updated store ratings', [
                'store_id' => $store->id,
                'new_rating' => $avgRating
            ]);
        } else {
            $this->info("[DRY RUN] Would update ratings for store ID {$store->id} to {$avgRating}.");
        }
        
        return true;
    }
}
