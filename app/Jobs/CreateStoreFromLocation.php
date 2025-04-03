<?php

namespace App\Jobs;

use App\Models\Location;
use App\Models\Store;
use App\Models\Article;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class CreateStoreFromLocation implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $locationId;
    protected $articleId;

    /**
     * Create a new job instance.
     *
     * @param int $locationId
     * @param int|null $articleId
     * @return void
     */
    public function __construct(int $locationId, ?int $articleId = null)
    {
        $this->locationId = $locationId;
        $this->articleId = $articleId;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $location = Location::find($this->locationId);
        
        if (!$location) {
            Log::error('[CreateStoreFromLocation] Location not found: ' . $this->locationId);
            return;
        }

        // Check if a store already exists for this location
        $store = Store::whereHas('location', function ($query) use ($location) {
            $query->where('locations.id', $location->id);
        })->first();

        if ($store) {
            Log::info('[CreateStoreFromLocation] Store already exists for location: ' . $location->id . ' with store id: ' . $store->id);
            return;
        }

        // Check if a store with the same name already exists to avoid duplicates
        if (Store::where('name', $location->name)->exists()) {
            Log::info('[CreateStoreFromLocation] Store with same name already exists for location: ' . $location->name);
            return;
        }

        Log::info('[CreateStoreFromLocation] Creating store for location: ' . $location->name);

        // Determine store status
        $status = Store::STATUS_ACTIVE;
        $smallLetterAddress = trim(strtolower($location->name));
        if (str_starts_with($smallLetterAddress, 'lorong') ||
            str_starts_with($smallLetterAddress, 'jalan') ||
            str_starts_with($smallLetterAddress, 'street')) {
            $status = Store::STATUS_INACTIVE;
        }

        // Create store
        $store = Store::create([
            'user_id' => null,
            'name' => $location->name,
            'manager_name' => null,
            'business_phone_no' => null,
            'business_hours' => null,
            'address' => $location->full_address,
            'address_postcode' => $location->zip_code,
            'lat' => $location->lat,
            'long' => $location->lng,
            'is_hq' => false,
            'state_id' => $location->state_id,
            'country_id' => $location->country_id,
            'status' => $status,
        ]);

        // Attach the location to the store
        $store->location()->attach($location->id);

        Log::info('[CreateStoreFromLocation] Store created for location: ' . $location->id . ' with store id: ' . $store->id);

        // If we have an article ID, process categories
        if ($this->articleId) {
            $article = Article::find($this->articleId);
            
            if ($article && ($article->categories->isNotEmpty() || $article->subCategories->isNotEmpty())) {
                try {
                    $articleCategoryIds = $article->categories->pluck('id');
                    $articleSubCategoryIds = $article->subCategories->pluck('id');
                    $allArticleCategoryIds = $articleCategoryIds->merge($articleSubCategoryIds);

                    $storeCategoriesToAttach = \App\Models\ArticleStoreCategory::whereIn('article_category_id', $allArticleCategoryIds)
                        ->pluck('merchant_category_id')
                        ->unique();

                    foreach ($storeCategoriesToAttach as $categoryId) {
                        try {
                            $store->categories()->attach($categoryId);
                            Log::info('[CreateStoreFromLocation] Store category attached: ' . $categoryId . ' to store: ' . $store->id);
                        } catch (\Exception $e) {
                            Log::error('[CreateStoreFromLocation] Error attaching store category: ' . $categoryId . ' to store: ' . $store->id . '. Error: ' . $e->getMessage());
                        }
                    }
                } catch (\Exception $e) {
                    Log::error('[CreateStoreFromLocation] Error processing categories for store: ' . $store->id . ' and article: ' . $article->id . '. Error: ' . $e->getMessage());
                }
            }
        }
    }
}
