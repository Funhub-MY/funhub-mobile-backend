<?php

namespace App\Jobs;

use App\Models\Location;
use App\Models\Store;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SyncLocationRatingAsStoreRating implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The location instance.
     *
     * @var \App\Models\Location
     */
    protected $location;

    /**
     * The user ID.
     *
     * @var int
     */
    protected $userId;

    /**
     * The article ID.
     *
     * @var int|null
     */
    protected $articleId;

    /**
     * Create a new job instance.
     *
     * @param \App\Models\Location $location
     * @param int $userId
     * @param int|null $articleId
     * @return void
     */
    public function __construct(Location $location, int $userId, ?int $articleId = null)
    {
        $this->location = $location;
        $this->userId = $userId;
        $this->articleId = $articleId;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        // Get all stores associated with this location
        $stores = $this->location->stores;

        if ($stores->isEmpty()) {
            Log::info('SyncLocationRatingAsStoreRating: No stores found for location', [
                'location_id' => $this->location->id
            ]);
            return;
        }

        // Get the rating for this user
        $locationRating = $this->location->ratings()
            ->where('user_id', $this->userId)
            ->first();

        if (!$locationRating) {
            Log::info('SyncLocationRatingAsStoreRating: No rating found for user', [
                'location_id' => $this->location->id,
                'user_id' => $this->userId
            ]);
            return;
        }

        $syncedCount = 0;

        foreach ($stores as $store) {
            // Check if store rating already exists for this user and article (if article is provided)
            $query = DB::table('store_ratings')
                ->where('store_id', $store->id)
                ->where('user_id', $this->userId);
                
            // If article ID is provided, include it in the check to prevent duplicates
            if ($this->articleId) {
                $existingArticleRating = clone $query;
                $existingArticleRating = $existingArticleRating->where('article_id', $this->articleId)->first();
                
                // If we already have a rating for this store, user, and article, use that one
                if ($existingArticleRating) {
                    $existingRating = $existingArticleRating;
                    
                    Log::info('SyncLocationRatingAsStoreRating: Found existing rating with matching article_id', [
                        'store_id' => $store->id,
                        'user_id' => $this->userId,
                        'article_id' => $this->articleId,
                        'rating_id' => $existingRating->id
                    ]);
                } else {
                    // Otherwise check if there's a rating without article_id that we can update
                    $existingRating = $query->whereNull('article_id')->first();
                    
                    // If no rating without article_id, check for any rating by this user for this store
                    if (!$existingRating) {
                        $existingRating = $query->first();
                    }
                }
            } else {
                // If no article ID provided, just check for any rating by this user for this store
                $existingRating = $query->first();
            }

            // Format dates properly for MySQL timestamp format
            $createdAt = $locationRating->created_at instanceof \DateTime 
                ? $locationRating->created_at->format('Y-m-d H:i:s')
                : date('Y-m-d H:i:s', strtotime($locationRating->created_at));
            
            $updatedAt = $locationRating->updated_at instanceof \DateTime 
                ? $locationRating->updated_at->format('Y-m-d H:i:s')
                : date('Y-m-d H:i:s', strtotime($locationRating->updated_at));

            if ($existingRating) {
                // Update existing rating
                DB::table('store_ratings')
                    ->where('id', $existingRating->id)
                    ->update([
                        'rating' => $locationRating->rating,
                        'article_id' => $this->articleId,
                        'updated_at' => $updatedAt
                    ]);
                
                Log::info('SyncLocationRatingAsStoreRating: Updated existing rating', [
                    'rating_id' => $existingRating->id,
                    'store_id' => $store->id,
                    'user_id' => $this->userId,
                    'article_id' => $this->articleId
                ]);
            } else {
                // Before creating, do one final check to prevent race conditions
                $finalCheck = DB::table('store_ratings')
                    ->where('store_id', $store->id)
                    ->where('user_id', $this->userId);
                    
                if ($this->articleId) {
                    $finalCheck = $finalCheck->where('article_id', $this->articleId);
                }
                
                if ($finalCheck->exists()) {
                    Log::info('SyncLocationRatingAsStoreRating: Prevented duplicate creation due to race condition', [
                        'store_id' => $store->id,
                        'user_id' => $this->userId,
                        'article_id' => $this->articleId
                    ]);
                    continue;
                }
                
                // Create new rating
                DB::table('store_ratings')->insert([
                    'store_id' => $store->id,
                    'user_id' => $this->userId,
                    'rating' => $locationRating->rating,
                    'article_id' => $this->articleId,
                    'created_at' => $createdAt,
                    'updated_at' => $updatedAt
                ]);
            }

            // Update store's average rating
            $averageRating = DB::table('store_ratings')
                ->where('store_id', $store->id)
                ->avg('rating') ?? 0;

            // Update store rating without triggering Scout
            DB::table('stores')
                ->where('id', $store->id)
                ->update([
                    'ratings' => $averageRating
                ]);

            // Dispatch job to update store search index
            dispatch(new IndexStore($store->id));

            $syncedCount++;

            Log::info('SyncLocationRatingAsStoreRating: Rating synced', [
                'location_id' => $this->location->id,
                'store_id' => $store->id,
                'user_id' => $this->userId,
                'rating' => $locationRating->rating,
                'article_id' => $this->articleId
            ]);
        }

        Log::info('SyncLocationRatingAsStoreRating: Completed', [
            'location_id' => $this->location->id,
            'synced_count' => $syncedCount
        ]);
    }
}
