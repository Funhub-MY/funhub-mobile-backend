<?php

namespace App\Console\Commands;

use App\Models\Location;
use App\Models\Article;
use App\Models\ArticleStoreCategory;
use Exception;
use App\Models\MerchantCategory;
use App\Models\Store;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class SyncArticlesLocationAsStores extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'articles:sync-location-as-stores
                            {--from= : Start date for article search (format: Y-m-d)}
                            {--to= : End date for article search (format: Y-m-d)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $fromDate = $this->option('from') ? Carbon::parse($this->option('from'))->startOfDay() : null;
        $toDate = $this->option('to') ? Carbon::parse($this->option('to'))->endOfDay() : null;

        if ($fromDate && $toDate) {
            $this->info("Searching for articles between {$fromDate} and {$toDate}");
        }

        // get all location with Article and doesnt currently linked to a Store
        $locations = Location::whereHas('articles', function ($query) use ($fromDate, $toDate) {
            $query->where('articles.status', Article::STATUS_PUBLISHED);
            if ($fromDate) {
                $query->where('articles.created_at', '>=', $fromDate);
            }
            if ($toDate) {
                $query->where('articles.created_at', '<=', $toDate);
            }
        })
        ->whereNotNull('google_id')
        ->get();

        $this->info('Total locations with articles that does not have stores: ' . $locations->count());
        Log::info('[SyncArticlesLocationAsStores] Total locations with articles that does not have stores: ' . $locations->count());

        // create a Store for each Location
        foreach($locations as $location)
        {
            try {
				$store = $location->stores->first();
				if (!$store) {
					// make sure same Store location never created before
					if (Store::where('name', $location->name)->exists()) {
						$this->info('Store same name already exists for location: ' . $location->id);
						continue;
					}

					$this->info('Creating store for location: ' . $location->name);

					$status = Store::STATUS_ACTIVE;
					// if full address starts with Lorong, Jalan or Street then set to unlisted first
					$smallLetterAddress = trim(strtolower($location->name));
					if (str_starts_with($smallLetterAddress, 'lorong') || str_starts_with($smallLetterAddress, 'jalan') || str_starts_with($smallLetterAddress, 'street')) {
						$status = Store::STATUS_INACTIVE;
					}

					// create store
					$store = Store::create([
						'user_id' => null,
						'name' => $location->name,
						'manager_name' => null,
						'business_phone_no' => null,
						'business_hours' => null,
						'address' => $location->full_address,
						'address_postcode' => $location->zip_code,
						'lang' => $location->lat,
						'long' => $location->lng,
						'is_hq' => false,
						'state_id' => $location->state_id,
						'country_id' => $location->country_id,
						'status' => $status, // all new stores will be inactive first
					]);

					// get google place types

					// also attach the location to the store
					$store->location()->attach($location->id);

					$this->info('Store created for location: ' . $location->id . ' with store id: ' . $store->id);
					Log::info('[SyncArticlesLocationAsStores] Store created for location: ' . $location->id . ' with store id: ' . $store->id);
				}

                // get first article latest
                $article = $location->articles()->where('status', Article::STATUS_PUBLISHED)->latest()->first();
                if ($article) {
					try {
						// Get article category IDs
						$articleCategoryIds = $article->categories->pluck('id');
						$articleSubCategoryIds = $article->subCategories->pluck('id');

						$allArticleCategoryIds = $articleCategoryIds->merge($articleSubCategoryIds);

						// Find mapped merchant categories from ArticleStoreCategory
						$storeCategoriesToAttach = ArticleStoreCategory::whereIn('article_category_id', $allArticleCategoryIds)
							->pluck('merchant_category_id')
							->unique();

						// Get current store categories
						$currentStoreCategories = $store->categories->pluck('id');

						// Determine categories to add
						$categoriesToAdd = $storeCategoriesToAttach->diff($currentStoreCategories);

						// Attach new categories
						foreach ($categoriesToAdd as $categoryId) {
							try {
								$store->categories()->attach($categoryId);
								Log::info('[SyncArticlesLocationAsStores] -- Store category attached: ' . $categoryId . ' to store: ' . $store->id);
								$this->info('-- Store category attached: ' . $categoryId . ' to store: ' . $store->id);
							} catch (Exception $e) {
								Log::error('[SyncArticlesLocationAsStores] Error attaching store category: ' . $categoryId . ' to store: ' . $store->id . '. Error: ' . $e->getMessage());
								$this->error('Error attaching store category: ' . $categoryId . ' to store: ' . $store->id . '. Error: ' . $e->getMessage());
							}
						}
					} catch (Exception $e) {
						Log::error('[SyncArticlesLocationAsStores] Error processing categories for store: ' . $store->id . ' and article: ' . $article->id . '. Error: ' . $e->getMessage());
						$this->error('Error processing categories for store: ' . $store->id . ' and article: ' . $article->id . '. Error: ' . $e->getMessage());
					}
				} else {
					$this->info('No published article found for location: ' . $location->id);
					Log::info('[SyncArticlesLocationAsStores] No published article found for location: ' . $location->id);
				}
            } catch (Exception $e) {
                Log::error('[SyncArticlesLocationAsStores] Error creating store for location: ' . $location->id . '. Error: ' . $e->getMessage());
                $this->error('Error creating store for location: ' . $location->id . '. Error: ' . $e->getMessage());
            }
        }

        return Command::SUCCESS;
    }
}
