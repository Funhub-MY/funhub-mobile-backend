<?php

namespace App\Console\Commands;

use App\Models\RatingCategory;
use App\Models\Store;
use App\Models\User;
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

        // Pull users
        $usersResponse = Http::withToken($authToken)->get($rootUrl . '/User');
        $usersData = $usersResponse->json();

        if ($usersResponse->status() !== 200) {
            $this->error("Failed to pull users from Bubble API: " . $usersResponse->statusText());
            return Command::FAILURE;
        }

        $usersData = $usersData['response']['results'];

        // Pull stores
        $storesResponse = Http::withToken($authToken)->get($rootUrl . '/Store');
        $storesData = $storesResponse->json();

        if ($storesResponse->status() !== 200) {
            $this->error("Failed to pull stores from Bubble API: " . $storesResponse->statusText());
            return Command::FAILURE;
        }

        $storesData = $storesData['response']['results'];

        // Pull reviews
        $reviewsResponse = Http::withToken($authToken)->get($rootUrl . '/Review');
        $reviewsData = $reviewsResponse->json();

        if ($reviewsResponse->status() !== 200) {
            $this->error("Failed to pull reviews from Bubble API: " . $reviewsResponse->statusText());
            return Command::FAILURE;
        }

        $reviewsData = $reviewsData['response']['results'];

        // log how m,any data got from bubble into Log
        Log::info('Bubble API Response Data Pull', [
            'users' => count($usersData),
            'reviews' => count($reviewsData),
            'stores' => count($storesData),
        ]);

        // Get unique users by phone number
        $uniqueUsers = $this->getUniqueUsersByPhoneNumber($usersData);

        // Process reviews and link them to users and stores
        if (is_array($reviewsData)) {
            foreach ($reviewsData as $review) {
                if (isset($review['Created By']) && isset($review['Store'])) {
                    $this->info("Processed review: " . $review['_id']);

                    $userId = $review['Created By'];
                    $storeId = $review['Store'];
                    $store = $this->getStoreById($storesData, $storeId);

                    if ($store && isset($store['funhub_store_id'])) {
                        $funhubStoreId = $store['funhub_store_id'];
                        $rating = $review['Rating'] ?? null;
                        $categories = $review['Topic'] ?? [];
                        $createdAt = $review['Created Date'] ?? null;

                        // Find the user by ID
                        foreach ($uniqueUsers as &$user) {
                            if (isset($user['_id']) && $user['_id'] === $userId) {
                                if (!isset($user['reviews'])) {
                                    $user['reviews'] = [];
                                }
                                $user['reviews'][] = [
                                    'funhub_store_id' => $funhubStoreId,
                                    'rating' => $rating,
                                    'categories' => $categories,
                                    'created_at' => $createdAt ? date('Y-m-d H:i:s', strtotime($createdAt)) : null
                                ];
                                break;
                            }
                        }
                    }
                } else {
                    $this->error("Review missing Created By or Store: " . json_encode($review));
                }
            }
        }

        // Sort reviews for each user by latest first, then oldest
        foreach ($uniqueUsers as &$user) {
            $this->info("Sorting reviews for user: " . $user['_id']);
            if (isset($user['reviews'])) {
                usort($user['reviews'], function($a, $b) {
                    return strtotime($b['created_at']) - strtotime($a['created_at']);
                });
            }
        }

        $availableRatingCategories = RatingCategory::all();

        // Output the formatted data
        foreach ($uniqueUsers as $phoneNumber => $user) {
            // create user if not exists match by phone number
            $authUser = User::where('phone_no', ltrim($user['Phone number'], '0'))
                ->where('phone_country_code', '60') // only MY
                ->first();

            if (!$authUser) {
              try {
                $authUser = User::create([
                    'name' => $user['User name'],
                    'phone_country_code' => '60',
                    'phone_no' => $user['Phone number'],
                ]);

                Log::info('[PullBubbleDataForUserStoreRatings] Bubble API User Created', [
                    'user_id' => $authUser->id,
                    'phone_no' => $user['Phone number'],
                    'name' => $user['User name'],
                ]);
              } catch (\Exception $e) {
                $this->error("Failed to create user: " . $user['User name']. ' phone_no: ' . $user['Phone number']);
                continue;
              }

              $this->info("Created: " . $user['User name']. ' with id: ' . $authUser->id. '  phone_no: ' . $user['Phone number']);
            } else {
                $this->info("Found existing user: " . $user['User name']. ' with id: ' . $authUser->id. '  phone_no: ' . $user['Phone number']);
            }

            if (isset($user['reviews'])) {
                foreach ($user['reviews'] as $review) {
                    // find store by funhub_store_id
                    $store = Store::where('id', $review['funhub_store_id'])->first();

                    if ($store && $authUser && !$store->where('external_review_id', $review['_id'])->exists()) {
                        $this->info("- Funhub Store ID: " . $review['funhub_store_id']);
                        // add store ratings of this user id
                        $rating = $store->storeRatings()->create([
                            'user_id' => $authUser->id,
                            'rating' => $review['rating'],
                            'comment' => (isset($review['comment'])) ? $review['comment'] : null,
                            'external_review_id' => $review['_id'],
                        ]);

                        $categories = $review['categories'];
                        // loop through each category thats available in availableRatingCategories and attach to rating
                        foreach ($categories as $category) {
                            if ($availableRatingCategories->contains('name', $category)) {
                                $rating->ratingCategories()->attach($availableRatingCategories->where('name', $category)->first()->id, ['user_id' => auth()->id()]);
                            }
                        }

                        Log::info('[PullBubbleDataForUserStoreRatings] Bubble API Store Rating Created', [
                            'user_id' => $authUser->id,
                            'store_id' => $review['funhub_store_id'],
                            'rating' => $review['rating'],
                            'categories' => $categories,
                        ]);

                        $this->info("  Rating: " . $review['rating']);

                    } else {
                        $this->error("Store ID: " . $review['funhub_store_id'] . " not found OR created before OR authed user not created. Review:" . json_encode($review));

                        Log::error('[PullBubbleDataForUserStoreRatings] Bubble API Store ID not found OR authed user not created', [
                            'user_id' => $user['_id'] ?? 'null',
                            'store_id' => $review['funhub_store_id'] ?? 'null',
                            'external_review_id' => $review['_id'] ?? 'null',
                        ]);
                    }
                }
            } else {
                $this->info("No reviews found for user: " . $user['User name']);
            }
        }

        $this->info('Bubble data sync completed successfully.');
        return Command::SUCCESS;
    }

    protected function getUniqueUsersByPhoneNumber($usersData) {
        $uniqueUsers = [];
        if (is_array($usersData)) {
            foreach ($usersData as $user) {
                if (isset($user['Phone number'])) {

                    $this->info("Processing user: " . $user['User name']. ' with non processed phone_no: ' . $user['Phone number']);

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
                        $this->info("Processed user: " . $user['User name']);
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
