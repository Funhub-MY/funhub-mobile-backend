<?php

namespace App\Console\Commands;

use App\Models\MerchantCategory;
use App\Models\Store;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class SyncArticlesLocationAsStores extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'articles:sync-location-as-stores';

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
        // get all location with Article and doesnt currently linked to a Store
        $locations = \App\Models\Location::whereHas('articles', function ($query) {
            $query->where('articles.status', \App\Models\Article::STATUS_PUBLISHED);
        })
        ->whereNotNull('google_id')
        ->doesntHave('stores')
        ->get();

        $this->info('Total locations with articles that does not have stores: ' . $locations->count());

        // create a Store for each Location
        foreach($locations as $location)
        {
            try {
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

                // get first article latest
                $article = $location->articles()->where('status', \App\Models\Article::STATUS_PUBLISHED)->latest()->first();
                if ($article) {
                    // get article categories match with merchant categories for Store
                    // if article->categories have "吃喝" then add Merchant category "美食" to store
                    $categories = $article->categories->pluck('name')->toArray();
                    $this->info('-- First Article categories: ' . implode(',', $categories));

					// Retrieve matching store categories from ArticleStoreCategory
					$articleCategoryIds = $article->categories->pluck('id');
					$storeCategories = \App\Models\ArticleStoreCategory::whereIn('article_category_id', $articleCategoryIds)
						->pluck('merchant_category_id')
						->unique();
					foreach ($storeCategories as $storeCategoryId) {
						try {
							$store->categories()->attach($storeCategoryId);
							$this->info('-- Store category attached: ' . $storeCategoryId);
						} catch (\Exception $e) {
							Log::error('Error attaching store category: ' . $storeCategoryId . ' to store: ' . $store->id);
							$this->error('Error attaching store category: ' . $storeCategoryId . ' to store: ' . $store->id);
						}
					}
//                    try {
//                        // if article->categories have "吃喝" then add Merchant category "美食" to store
//                        if (in_array('吃喝', $categories)) {
//                            $foodCategory = MerchantCategory::where('name', '美食')->first();
//                            if ($foodCategory) {
//                                $store->categories()->attach($foodCategory->id);
//                                $this->info('-- Store category attached: ' . $foodCategory->name);
//                            }
//                        }
//
//                        if (in_array('休闲', $categories) || in_array('旅游', $categories) || in_array('娱乐', $categories)) {
//                            $shoppingCategory = MerchantCategory::where('name', '玩乐')->first();
//                            if ($shoppingCategory) {
//                                $store->categories()->attach($shoppingCategory->id);
//                                $this->info('-- Store category attached: ' . $shoppingCategory->name);
//                            }
//                        }
//                    } catch (\Exception $e) {
//                        Log::error('Error attaching categories to store: ' . $store->id . ' for article: ' . $article->id . '. Error: ' . $e->getMessage());
//                        $this->error('Error attaching categories to store: ' . $store->id . ' for article: ' . $article->id . '. Error: ' . $e->getMessage());
//                    }
                }

                $this->info('Store created for location: ' . $location->id . ' with store id: ' . $store->id);
                Log::info('Store created for location: ' . $location->id . ' with store id: ' . $store->id);
            } catch (\Exception $e) {
                Log::error('Error creating store for location: ' . $location->id . '. Error: ' . $e->getMessage());
                $this->error('Error creating store for location: ' . $location->id . '. Error: ' . $e->getMessage());
            }
        }

        return Command::SUCCESS;
    }
}
