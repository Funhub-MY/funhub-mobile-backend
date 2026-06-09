<?php

namespace App\Jobs;

use App\Models\Article;
use App\Models\Location;
use App\Models\Store;
use App\Services\LocationStoreLinker;
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

			$article = $this->articleId ? Article::find($this->articleId) : null;
			$store = LocationStoreLinker::resolveOrCreateForLocation($location, $article);

			if ($this->articleId && $store) {
				if (!$article) {
					$article = Article::find($this->articleId);
				}

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

						$storeCategoriesToAttach = \App\Models\ArticleStoreCategory::whereIn('article_category_id', $allArticleCategoryIds)
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
							} catch (\Exception $e) {
								Log::error('[CreateStoreFromLocation] Error attaching store category', [
									'store_id' => $store->id,
									'category_id' => $categoryId,
									'error_message' => $e->getMessage(),
									'error_trace' => $e->getTraceAsString()
								]);
							}
						}
					} catch (\Exception $e) {
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
		} catch (\Exception $e) {
			Log::error('[CreateStoreFromLocation] Unhandled exception in job', [
				'location_id' => $this->locationId,
				'article_id' => $this->articleId,
				'error_message' => $e->getMessage(),
				'error_trace' => $e->getTraceAsString()
			]);
		}
    }
}
