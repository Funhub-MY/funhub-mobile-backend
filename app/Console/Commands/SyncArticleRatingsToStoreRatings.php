<?php

namespace App\Console\Commands;

use App\Models\Article;
use App\Models\Store;
use App\Models\StoreRating;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class SyncArticleRatingsToStoreRatings extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'articles:sync-ratings-to-store-ratings';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sync article ratings to store ratings based on article location.';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        // loop Article has location and is published
        $articles = Article::whereHas('location')
            ->where('status', Article::STATUS_PUBLISHED)
            ->get();

        // count
        $this->info("[SyncArticleRatingsToStoreRatings] Total published articles with location tagged to process: " . $articles->count());

        foreach ($articles as $article) {
            // check if store_rating with article_id exists?
            $storeRating = StoreRating::where('article_id', $article->id)->first();
            if ($storeRating) {
                $this->info("[SyncArticleRatingsToStoreRatings] StoreRating with article_id {$article->id} already exists, skipping...");
                continue;
            }

            $location = $article->location()->first();
            $stores = $location->stores;
            if ($stores->isEmpty()) {
                $this->info("[SyncArticleRatingsToStoreRatings] Location {$location->id} does not have any store, skipping...");
                continue;
            }

            $storeIds = $stores->pluck('id')->toArray();

            $this->info("[SyncArticleRatingsToStoreRatings] Processing article {$article->id} with store_ids: " . implode(',', $storeIds));

            // if does not exists, we need to create a new store rating based on Article location ratings
            foreach($storeIds as $storeId)
            {
                $rating =  $location->ratings()->where('user_id', $article->user_id)->first();

                if (!$rating) {
                    $this->info("[SyncArticleRatingsToStoreRatings] Rating not found for user_id {$article->user_id} in location {$location->id}, skipping...");
                    continue;
                }

                $storeRating = StoreRating::create([
                    'store_id' => $storeId,
                    'user_id' => $article->user_id,
                    'rating' => $rating->rating,
                    'comment' => null,
                    'article_id' => $article->id,
                    'created_at' => $article->created_at,
                    'updated_at' => $article->updated_at
                ]);

                // update store->ratings avg
                $store = Store::where('id', $storeId)->first();
                if ($store) {
                    $store->update([
                        'ratings' => StoreRating::where('store_id', $store->id)->avg('rating')
                    ]);
                }

                $this->info("[SyncArticleRatingsToStoreRatings] StoreRating created for store_id {$storeId} with article_id {$article->id}");

                Log::info('[SyncArticleRatingsToStoreRatings] Store rating created', [
                    'store_id' => $storeId,
                    'user_id' => $article->user_id,
                    'rating' => $rating->rating,
                    'article_id' => $article->id
                ]);
            }
        }

        // process unpublished or deleted articles
        // get all StoreRating with article_id, then loop through each aricles see if status is unpublished or not found then remove the rating
        $storeRatings = StoreRating::whereNotNull('article_id')->get();

        $this->info("[SyncArticleRatingsToStoreRatings] Check for removal of articles, total StoreRatings with article_id to process: " . $storeRatings->count());

        foreach ($storeRatings as $storeRating) {
            $article = Article::find($storeRating->article_id);

            if (!$article || $article->status !== Article::STATUS_PUBLISHED) {
                $storeRating->delete();

                // update store->ratings avg
                $store = Store::where('id', $storeRating->store_id)->first();
                if ($store) {
                    $store->update([
                        'ratings' => StoreRating::where('store_id', $store->id)->avg('rating')
                    ]);
                }
                $this->info("[SyncArticleRatingsToStoreRatings] Article with id {$storeRating->article_id} not found or unpublished, removing store rating...");
            }
        }
        return Command::SUCCESS;
    }
}
