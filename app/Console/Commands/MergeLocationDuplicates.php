<?php

namespace App\Console\Commands;

use App\Models\Location;
use App\Models\Store;
use App\Models\LocationRating;
use App\Models\StoreRating;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class MergeLocationDuplicates extends Command
{
    protected $signature = 'locations:merge-duplicates {--dry-run : Run without making changes}';
    protected $description = 'Merge duplicate locations, using store locations as primary';

    private $mergedLocations = [];

    public function handle()
    {
        $isDryRun = $this->option('dry-run');
        $this->info($isDryRun ? 'Running in dry-run mode' : 'Running in live mode');

        // Get all stores with user_id and locations
        $stores = Store::whereNotNull('user_id')
            ->whereHas('location')
            ->with('location')
            ->get();

        $this->info("Found {$stores->count()} stores with locations to process");
        $mergedCount = 0;

        foreach ($stores as $store) {
            $primaryLocation = $store->location->first();
            if (!$primaryLocation) {
                continue;
            }
            $this->info("\n----------------------------------------");

            $this->info("\nProcessing store: {$store->name}");
            $this->info("Primary location [ID: {$primaryLocation->id}]: {$primaryLocation->name}");

            // Find potential duplicates using fuzzy matching
            $duplicates = Location::where('id', '!=', $primaryLocation->id)
                ->where('is_mall', false) // Skip mall locations
                ->where(function ($query) use ($primaryLocation) {
                    // Match by exact name
                    $query->where('name', 'like', $primaryLocation->name)
                        // Or by similar name (removing spaces and special chars)
                        ->orWhere('name', 'like', '%' . Str::slug($primaryLocation->name, ' ') . '%');
                })
                ->withCount(['articles' => function ($query) {
                    $query->where('status', 'published');
                }])
                ->get();

            if ($duplicates->isEmpty()) {
                continue;
            }

            $this->info("Found {$duplicates->count()} potential duplicates");

            foreach ($duplicates as $duplicate) {
                $this->info("Duplicate location [ID: {$duplicate->id}]: {$duplicate->name}");
                $this->info("Articles linked: {$duplicate->articles_count}");
                $this->info("Will be merged into -> Primary [ID: {$primaryLocation->id}]: {$primaryLocation->name}");
                
                // Skip if not really a duplicate
                if (!$this->shouldMergeLocations($primaryLocation, $duplicate)) {
                    $this->info("Skipping - not similar enough");
                    continue;
                }

                // Track the merge regardless of dry-run
                $this->mergedLocations[] = [
                    'duplicate_id' => $duplicate->id,
                    'duplicate_name' => $duplicate->name,
                    'articles_count' => $duplicate->articles_count,
                    'primary_id' => $primaryLocation->id,
                    'primary_name' => $primaryLocation->name,
                    'store_id' => $store->id,
                    'store_name' => $store->name
                ];

                if ($isDryRun) {
                    $this->info("Would merge {$duplicate->name} into {$primaryLocation->name}");
                    continue;
                }

                try {
                    DB::beginTransaction();

                    // 1. Update all locatables
                    $this->mergeLocatables($primaryLocation, $duplicate);

                    // 2. Merge location ratings
                    $this->mergeLocationRatings($primaryLocation, $duplicate);

                    // 3. Merge store ratings if applicable
                    if ($store->ratings()->exists()) {
                        $this->mergeStoreRatings($store, $duplicate);
                    }

                    // 4. Log the merge
                    Log::info('Merged duplicate location', [
                        'primary_location_id' => $primaryLocation->id,
                        'duplicate_location_id' => $duplicate->id,
                        'store_id' => $store->id
                    ]);

                    // 5. Delete the duplicate location
                    $duplicate->delete();

                    DB::commit();
                    $mergedCount++;

                    $this->info("Successfully merged locations");

                } catch (\Exception $e) {
                    DB::rollBack();
                    $this->error("Error merging locations: {$e->getMessage()}");
                    Log::error('Error merging locations', [
                        'error' => $e->getMessage(),
                        'primary_location_id' => $primaryLocation->id,
                        'duplicate_location_id' => $duplicate->id
                    ]);
                }
            }
        }

        $actionText = $isDryRun ? 'Would merge' : 'Merged';
        $this->info("\nComplete! {$actionText} {$mergedCount} locations");

        if (!empty($this->mergedLocations)) {
            $this->info("\n\nMerged Locations Summary:");
            $this->info("----------------------------------------");
            foreach ($this->mergedLocations as $merge) {
                $this->info(
                    "-- > Duplicate [ID: {$merge['duplicate_id']}] {$merge['duplicate_name']} " .
                    "with {$merge['articles_count']} articles merged into \n" .
                    "Primary [ID: {$merge['primary_id']}] {$merge['primary_name']} " .
                    "of Store [ID: {$merge['store_id']}] {$merge['store_name']}\n"
                );
            }
            $this->info("----------------------------------------");
        }

        return Command::SUCCESS;
    }

    private function shouldMergeLocations(Location $primary, Location $duplicate): bool
    {
        // If names are exactly the same, definitely merge
        if (Str::lower($primary->name) === Str::lower($duplicate->name)) {
            return true;
        }

        // If names are very similar and in same area
        $primarySlug = Str::slug($primary->name);
        $duplicateSlug = Str::slug($duplicate->name);
        $namesSimilar = similar_text($primarySlug, $duplicateSlug) > 80;
        
        $sameArea = $primary->city === $duplicate->city && 
                   $primary->state_id === $duplicate->state_id;

        return $namesSimilar && $sameArea;
    }

    private function mergeLocatables(Location $primary, Location $duplicate): void
    {
        // Update all locatable relationships to point to the primary location
        DB::table('locatables')
            ->where('location_id', $duplicate->id)
            ->update(['location_id' => $primary->id]);
    }

    private function mergeLocationRatings(Location $primary, Location $duplicate): void
    {
        // Get all ratings from the duplicate
        $duplicateRatings = LocationRating::where('location_id', $duplicate->id)->get();

        foreach ($duplicateRatings as $rating) {
            // Check if a similar rating exists
            $existingRating = LocationRating::where('location_id', $primary->id)
                ->where('user_id', $rating->user_id)
                ->first();

            if ($existingRating) {
                // Keep the most recent rating
                if ($rating->created_at > $existingRating->created_at) {
                    $existingRating->update([
                        'rating' => $rating->rating,
                        'comment' => $rating->comment,
                        'updated_at' => $rating->updated_at
                    ]);
                }
                $rating->delete();
            } else {
                // Move rating to primary location
                $rating->update(['location_id' => $primary->id]);
            }
        }
    }

    private function mergeStoreRatings(Store $store, Location $duplicate): void
    {
        // Find any stores associated with the duplicate location
        $duplicateStores = Store::whereHas('location', function ($query) use ($duplicate) {
            $query->where('locations.id', $duplicate->id);
        })->get();

        foreach ($duplicateStores as $duplicateStore) {
            $duplicateRatings = StoreRating::where('store_id', $duplicateStore->id)->get();

            foreach ($duplicateRatings as $rating) {
                // Check if a similar rating exists
                $existingRating = StoreRating::where('store_id', $store->id)
                    ->where('user_id', $rating->user_id)
                    ->first();

                if ($existingRating) {
                    // Keep the most recent rating
                    if ($rating->created_at > $existingRating->created_at) {
                        $existingRating->update([
                            'rating' => $rating->rating,
                            'comment' => $rating->comment,
                            'updated_at' => $rating->updated_at
                        ]);
                    }
                    $rating->delete();
                } else {
                    // Move rating to primary store
                    $rating->update(['store_id' => $store->id]);
                }
            }
        }
    }
}
