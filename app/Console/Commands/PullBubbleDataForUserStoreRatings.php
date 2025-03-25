<?php

namespace App\Console\Commands;

use App\Jobs\IndexStore;
use App\Models\RatingCategory;
use App\Models\Store;
use App\Models\StoreRating;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class PullBubbleDataForUserStoreRatings extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'bubble:sync-user-store-ratings';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Pull Bubble API data for users, stores, and ratings';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $rootUrl = config('services.bubble.root_url');
        $authToken = config('services.bubble.api_key');

        if (empty($rootUrl) || empty($authToken)) {
            $this->error("Bubble API credentials not configured");
            return Command::FAILURE;
        }

        try {
            $this->info("Starting Bubble API sync");
            
            // Get all available rating categories once for reuse
            $availableRatingCategories = RatingCategory::all();
            $this->info("Loaded " . $availableRatingCategories->count() . " rating categories");
            
            // Get existing review IDs from our database to avoid duplicates
            $existingReviewIds = StoreRating::whereNotNull('external_review_id')
                ->pluck('external_review_id')
                ->toArray();
            
            $this->info("Found " . count($existingReviewIds) . " existing reviews in our database");
            
            // Initialize pagination variables for reviews
            $cursor = 0;
            $batchSize = 50; // Process reviews in batches of 50
            $hasMoreReviews = true;
            $processedCount = 0;
            $skippedCount = 0;
            
            // Process reviews in batches
            while ($hasMoreReviews) {
                $this->info("\nFetching reviews batch from Bubble API (cursor: {$cursor}, limit: {$batchSize})");
                
                // Prepare constraints to exclude existing reviews
                $constraints = [];
                
                // Only add not_in constraint if we have existing reviews
                if (!empty($existingReviewIds)) {
                    // Due to potential API limitations with large arrays, we'll limit the number of IDs per request
                    // If there are too many IDs, we'll just use the filter method later
                    if (count($existingReviewIds) <= 100) {
                        $constraints[] = [
                            'key' => '_id',
                            'constraint_type' => 'not in',
                            'value' => $existingReviewIds
                        ];
                    }
                }
                
                $params = [
                    'cursor' => $cursor,
                    'limit' => $batchSize
                ];
                
                // Only add constraints if we have any
                if (!empty($constraints)) {
                    $params['constraints'] = json_encode($constraints);
                }
                
                // Fetch reviews with pagination and constraints
                $reviewsResponse = Http::withToken($authToken)->get($rootUrl . '/Review', $params);
                
                if ($reviewsResponse->status() !== 200) {
                    $this->error("Failed to pull reviews from Bubble API: " . $reviewsResponse->status());
                    return Command::FAILURE;
                }
                
                $reviewsData = $reviewsResponse->json();
                $reviews = collect($reviewsData['response']['results']);
                $remaining = $reviewsData['response']['remaining'] ?? 0;
                
                $this->info("Fetched " . $reviews->count() . " reviews (remaining: {$remaining})");
                
                if ($reviews->isEmpty()) {
                    $this->info("No more reviews to process");
                    break;
                }
                
                // If we have too many existing IDs or couldn't use the constraint, filter them out here
                if (!empty($existingReviewIds) && (count($existingReviewIds) > 100 || empty($constraints))) {
                    $newReviews = $reviews->filter(function ($review) use ($existingReviewIds) {
                        return !in_array($review['_id'], $existingReviewIds);
                    });
                    $this->info("Filtered out " . ($reviews->count() - $newReviews->count()) . " already existing reviews");
                } else {
                    // If we used constraints, all reviews should be new
                    $newReviews = $reviews;
                }
                
                $this->info("Found " . $newReviews->count() . " new reviews to process");
                
                // Process each new review in this batch
                foreach ($newReviews as $review) {
                    $this->info("--------------------------------------------------------------------------------");
                    $this->info("Processing review: " . $review['_id']);
                    
                    // Skip reviews without required data
                    if (!isset($review['Store'])) {
                        $this->error('Skipping review without store reference');
                        $skippedCount++;
                        continue;
                    }
                    
                    if (!isset($review['User Name'])) {
                        $this->error('Skipping review without user reference');
                        $skippedCount++;
                        continue;
                    }
                    
                    // Get the store data using constraints
                    $storeId = $review['Store'];
                    $storeConstraints = json_encode([[
                        'key' => '_id',
                        'constraint_type' => 'equals',
                        'value' => $storeId
                    ]]);
                    
                    $this->info("Fetching store data for ID: " . $storeId);
                    $storeResponse = Http::withToken($authToken)
                        ->get($rootUrl . '/Store', [
                            'constraints' => $storeConstraints
                        ]);
                    
                    if ($storeResponse->status() !== 200) {
                        $this->error("Failed to fetch store data: " . $storeResponse->status());
                        $skippedCount++;
                        continue;
                    }
                    
                    $storeData = $storeResponse->json();
                    $bubbleStore = collect($storeData['response']['results'])->first();
                    
                    if (!$bubbleStore) {
                        $this->error("Store not found in Bubble: " . $storeId);
                        $skippedCount++;
                        continue;
                    }
                    
                    // Check if the Bubble store has funhub_store_id
                    if (!isset($bubbleStore['funhub_store_id']) || empty($bubbleStore['funhub_store_id'])) {
                        $this->error("Store missing funhub_store_id: " . $bubbleStore['Store Name'] ?? 'Unknown');
                        $skippedCount++;
                        continue;
                    }
                    
                    // Find the store in our database
                    $store = Store::find($bubbleStore['funhub_store_id']);
                    if (!$store) {
                        $this->error("Store not found in our database: ID " . $bubbleStore['funhub_store_id']);
                        $skippedCount++;
                        continue;
                    }
                    
                    // Get the user data using constraints
                    $userId = $review['User Name'];
                    $userConstraints = json_encode([[
                        'key' => '_id',
                        'constraint_type' => 'equals',
                        'value' => $userId
                    ]]);
                    
                    $this->info("Fetching user data for ID: " . $userId);
                    $userResponse = Http::withToken($authToken)
                        ->get($rootUrl . '/User', [
                            'constraints' => $userConstraints
                        ]);
                    
                    if ($userResponse->status() !== 200) {
                        $this->error("Failed to fetch user data: " . $userResponse->status());
                        $skippedCount++;
                        continue;
                    }
                    
                    $userData = $userResponse->json();
                    $bubbleUser = collect($userData['response']['results'])->first();
                    
                    if (!$bubbleUser) {
                        $this->error("User not found in Bubble: " . $userId);
                        $skippedCount++;
                        continue;
                    }
                    
                    // Get or create the user in our database
                    $authUser = $this->getAuthUser($bubbleUser);
                    if (!$authUser) {
                        $this->error("Failed to get or create user in our database");
                        $skippedCount++;
                        continue;
                    }
                    
                    $this->info("User found: " . $bubbleUser['User name'] . " (ID: " . $authUser->id . ")");
                    $this->info("Store found: " . $store->name . " (ID: " . $store->id . ")");
                    
                    // Create the rating
                    $createdDate = Carbon::parse($review['Created Date']);
                    
                    $rating = $store->storeRatings()->create([
                        'user_id' => $authUser->id,
                        'rating' => $review['Rating'],
                        'comment' => $review['Comments'] ?? null,
                        'external_review_id' => $review['_id'],
                        'created_at' => $createdDate,
                        'updated_at' => $createdDate,
                    ]);
                    
                    $this->info("Created rating for store: " . $store->name . " by user: " . $authUser->name);
                    Log::info("Created rating for store: " . $store->name . " by user: " . $authUser->name, [
                        'store_id' => $store->id,
                        'review_data' => $review,
                    ]);
                    
                    // Dispatch job to update store in search index
                    IndexStore::dispatch($store->id);
                    $this->info("Dispatched IndexStore job for store ID: " . $store->id);
                    
                    // Process categories if they exist
                    if (isset($review['Topic']) && is_array($review['Topic'])) {
                        foreach ($review['Topic'] as $categoryName) {
                            if ($availableRatingCategories->contains('name', $categoryName)) {
                                $this->info("Attaching rating category: " . $categoryName);
                                $rating->ratingCategories()->attach(
                                    $availableRatingCategories->where('name', $categoryName)->first()->id,
                                    ['user_id' => $authUser->id]
                                );
                            }
                        }
                    }
                    
                    $processedCount++;
                }
                
                // Update cursor for next batch
                $cursor += $reviews->count();
                
                // If there are no more reviews to fetch, stop
                if ($remaining <= 0) {
                    $hasMoreReviews = false;
                }
            }
            
            $this->info("\n=== Sync Summary ===");
            $this->info("Processed: " . $processedCount . " reviews");
            $this->info("Skipped: " . $skippedCount . " reviews");
            $this->info("Total: " . ($processedCount + $skippedCount) . " reviews");
            
            return Command::SUCCESS;
            
        } catch (\Exception $e) {
            $this->error("An error occurred: " . $e->getMessage());
            return Command::FAILURE;
        }
    }

    protected function getAuthUser($userData) {
        if (!isset($userData['Phone number'])) {
            $this->error("-- User data does not have phone number: " . json_encode($userData));
            return null;
        }
        $authUser = User::where('phone_no', trim(ltrim($userData['Phone number'], '0')))
            ->where('phone_country_code', '60') // only MY
            ->first();

        if (!$authUser) {
            try {
                $authUser = User::create([
                    'name' => $userData['User name'],
                    'phone_country_code' => '60',
                    'phone_no' => $userData['Phone number'],
                ]);

                $this->info("Created: " . $userData['User name']. ' with id: ' . $authUser->id. '  phone_no: ' . $userData['Phone number']);

                Log::info('[PullBubbleDataForUserStoreRatings] Bubble API User Created', [
                    'user_id' => $authUser->id,
                    'phone_no' => $userData['Phone number'],
                    'name' => $userData['User name'],
                ]);
            } catch (\Exception $e) {
                $this->error("Failed to create user: " . $userData['User name']. ' phone_no: ' . $userData['Phone number']);
                return null;
            }
        } else {
            $this->info("Found existing user: " . $userData['User name']. ' with id: ' . $authUser->id. '  phone_no: ' . $userData['Phone number']);
        }
        return $authUser;
    }

    protected function getUniqueUsersByPhoneNumber($usersData) {
        $uniqueUsers = [];
        if (is_array($usersData)) {
            foreach ($usersData as $user) {
                if (isset($user['Phone number'])) {

                    // $this->info("Processing user: " . $user['User name']. ' with non processed phone_no: ' . $user['Phone number']);

                    // remove any + and prefix 0 from phone number
                    $phoneNumber = ltrim($user['Phone number'], '0');
                    $phoneNumber = str_replace('+', '', $phoneNumber);

                    // ensure phone number is at least 9 digits
                    if (strlen($phoneNumber) < 9) {
                        $this->info("Skipping user: " . $user['User name']. ' with non processed phone_no: ' . $user['Phone number']);
                        continue;
                    }

                    $phoneNumber = $user['Phone number'];

                    if (!isset($uniqueUsers[$phoneNumber])) {
                        $uniqueUsers[$phoneNumber] = $user;
                        // $this->info("Processed user: " . $user['User name']);
                    }
                }
            }
        }
        return $uniqueUsers;
    }

    protected function getStoreById($storesData, $storeId) {
        if (is_array($storesData)) {
            foreach ($storesData as $store) {
                if (isset($store['_id']) && $store['_id'] === $storeId) {
                    return $store;
                }
            }
        }
        return null;
    }
}
