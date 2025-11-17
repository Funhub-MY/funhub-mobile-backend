<?php

namespace App\Jobs;

use Exception;
use App\Models\ArticleStoreCategory;
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

		Log::info('[CreateStoreFromLocation] Job instantiated', [
			'location_id' => $locationId,
			'article_id' => $articleId
		]);
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
		try {
			$location = Location::find($this->locationId);

			if (!$location) {
				Log::error('[CreateStoreFromLocation] Location not found', [
					'location_id' => $this->locationId
				]);
				return;
			}

			// Check if a store already exists for this location
			$existingStore = Store::whereHas('location', function ($query) use ($location) {
				$query->where('locations.id', $location->id);
			})->first();

			$store = null;

			if ($existingStore) {
				Log::info('[CreateStoreFromLocation] Store already exists for location', [
					'location_id' => $location->id,
					'store_id' => $existingStore->id,
					'store_name' => $existingStore->name
				]);
				$store = $existingStore;
			} else {
				// Check if a store with the same name already exists to avoid duplicates
				$storeWithSameName = Store::where('name', $location->name)->first();
				if ($storeWithSameName) {
					Log::info('[CreateStoreFromLocation] Store with same name already exists', [
						'location_id' => $location->id,
						'location_name' => $location->name,
						'existing_store_id' => $storeWithSameName->id
					]);
					$store = $storeWithSameName;
				} else {
					Log::info('[CreateStoreFromLocation] No store with same name exists, proceeding with creation', [
						'location_name' => $location->name
					]);

					// Determine store status
					$status = Store::STATUS_ACTIVE;
					$smallLetterAddress = trim(strtolower($location->name));

					if (str_starts_with($smallLetterAddress, 'lorong') ||
						str_starts_with($smallLetterAddress, 'jalan') ||
						str_starts_with($smallLetterAddress, 'street')) {
						$status = Store::STATUS_INACTIVE;
						Log::info('[CreateStoreFromLocation] Setting store status to INACTIVE based on name pattern', [
							'location_name' => $location->name,
							'pattern_matched' => true
						]);
					} else {
						Log::info('[CreateStoreFromLocation] Setting store status to ACTIVE', [
							'location_name' => $location->name
						]);
					}

					// Create store
					try {
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

						Log::info('[CreateStoreFromLocation] Store created successfully', [
							'store_id' => $store->id,
							'store_name' => $store->name
						]);
					} catch (Exception $e) {
						Log::error('[CreateStoreFromLocation] Failed to create store', [
							'location_id' => $location->id,
							'error_message' => $e->getMessage(),
							'error_trace' => $e->getTraceAsString()
						]);
						throw $e;
					}

					// Attach the location to the store
					try {
						$store->location()->attach($location->id);

						Log::info('[CreateStoreFromLocation] Location attached to store successfully', [
							'store_id' => $store->id,
							'location_id' => $location->id
						]);
					} catch (Exception $e) {
						Log::error('[CreateStoreFromLocation] Failed to attach location to store', [
							'store_id' => $store->id,
							'location_id' => $location->id,
							'error_message' => $e->getMessage(),
							'error_trace' => $e->getTraceAsString()
						]);
						throw $e;
					}
				}
			}

			// If we have an article ID, process categories
			if ($this->articleId && $store) {
				$article = Article::find($this->articleId);

				if (!$article) {
					Log::warning('[CreateStoreFromLocation] Article not found', [
						'article_id' => $this->articleId
					]);
					return;
				}

				Log::info('[CreateStoreFromLocation] Article found', [
					'article_id' => $article->id,
					'category_count' => $article->categories->count(),
					'subcategory_count' => $article->subCategories->count()
				]);

				if ($article->categories->isNotEmpty() || $article->subCategories->isNotEmpty()) {
					try {
						$articleCategoryIds = $article->categories->pluck('id');
						$articleSubCategoryIds = $article->subCategories->pluck('id');
						$allArticleCategoryIds = $articleCategoryIds->merge($articleSubCategoryIds);

						Log::info('[CreateStoreFromLocation] Article category IDs', [
							'article_id' => $article->id,
							'category_ids' => $articleCategoryIds->toArray(),
							'subcategory_ids' => $articleSubCategoryIds->toArray()
						]);

						$storeCategoriesToAttach = ArticleStoreCategory::whereIn('article_category_id', $allArticleCategoryIds)
							->pluck('merchant_category_id')
							->unique();

						Log::info('[CreateStoreFromLocation] Store categories to attach', [
							'store_id' => $store->id,
							'category_count' => $storeCategoriesToAttach->count(),
							'category_ids' => $storeCategoriesToAttach->toArray()
						]);

						// Get current store categories to avoid duplicates
						$currentStoreCategories = $store->categories->pluck('id');
						
						// Determine categories to add (only new ones)
						$categoriesToAdd = $storeCategoriesToAttach->diff($currentStoreCategories);

						foreach ($categoriesToAdd as $categoryId) {
							try {
								Log::info('[CreateStoreFromLocation] Attaching category to store', [
									'store_id' => $store->id,
									'category_id' => $categoryId
								]);

								$store->categories()->attach($categoryId);
							} catch (Exception $e) {
								Log::error('[CreateStoreFromLocation] Error attaching store category', [
									'store_id' => $store->id,
									'category_id' => $categoryId,
									'error_message' => $e->getMessage(),
									'error_trace' => $e->getTraceAsString()
								]);
							}
						}
					} catch (Exception $e) {
						Log::error('[CreateStoreFromLocation] Error processing categories', [
							'store_id' => $store->id,
							'article_id' => $article->id,
							'error_message' => $e->getMessage(),
							'error_trace' => $e->getTraceAsString()
						]);
					}
				} else {
					Log::info('[CreateStoreFromLocation] No categories found for article', [
						'article_id' => $article->id
					]);
				}
			}
		} catch (Exception $e) {
			Log::error('[CreateStoreFromLocation] Unhandled exception in job', [
				'location_id' => $this->locationId,
				'article_id' => $this->articleId,
				'error_message' => $e->getMessage(),
				'error_trace' => $e->getTraceAsString()
			]);
		}
    }
}
