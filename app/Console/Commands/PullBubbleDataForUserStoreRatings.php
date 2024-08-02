<?php

namespace App\Console\Commands;

use App\Models\RatingCategory;
use App\Models\Store;
use App\Models\StoreRating;
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

        // // Get unique users by phone number
        // $uniqueUsers = $this->getUniqueUsersByPhoneNumber($usersData);

        // // Process reviews and link them to users and stores
        // if (is_array($reviewsData)) {
        //     foreach ($reviewsData as $review) {
        //         if (isset($review['Created By']) && isset($review['Store'])) {

        //             $userId = $review['Created By'];
        //             $storeId = $review['Store'];
        //             $store = $this->getStoreById($storesData, $storeId);

        //             if ($store && isset($store['funhub_store_id'])) {
        //                 $funhubStoreId = $store['funhub_store_id'];
        //                 $rating = $review['Rating'] ?? null;
        //                 $categories = $review['Topic'] ?? [];
        //                 $createdAt = $review['Created Date'] ?? null;

        //                 // Find the user by ID
        //                 foreach ($uniqueUsers as &$user) {
        //                     if (isset($user['_id']) && $user['_id'] === $userId) {
        //                         if (!isset($user['reviews'])) {
        //                             $user['reviews'] = [];
        //                         }
        //                         $user['reviews'][] = [
        //                             '_id' => $review['_id'],
        //                             'funhub_store_id' => $funhubStoreId,
        //                             'rating' => $rating,
        //                             'categories' => $categories,
        //                             'created_at' => $createdAt ? date('Y-m-d H:i:s', strtotime($createdAt)) : null
        //                         ];

        //                         $this->info("-- Store Rating with external_review_id: " . $review['_id'] . " append to user: " . $user['User name']);
        //                         break;
        //                     }
        //                 }
        //             }
        //         } else {
        //             $this->error("Review missing Created By or Store: " . json_encode($review));
        //         }
        //     }
        // }

        // // Sort reviews for each user by latest first, then oldest
        // foreach ($uniqueUsers as &$user) {
        //     $this->info("Sorting reviews for user: " . $user['_id']);
        //     if (isset($user['reviews'])) {
        //         usort($user['reviews'], function($a, $b) {
        //             return strtotime($b['created_at']) - strtotime($a['created_at']);
        //         });
        //     }
        // }

        // collect all results using laravel collection
        $reviewsData = collect($reviewsData);
        $usersData = collect($usersData);
        $storesData = collect($storesData);

        // reviewsData sort by Created Date
        $reviewsData = $reviewsData->sortByDesc('Created Date');

        $availableRatingCategories = RatingCategory::all();
        foreach ($reviewsData as $review) {

            //print new line
            $this->info("--------------------------------------------------------------------------------");
            $this->info("Process Review data: " . json_encode($review));


            if (!isset($review['Store'])) {
                $this->error('Skipping .. Review without store');
                continue;
            }

            if (!isset($review['User Name'])) {
                $this->error('Skipping .. Review without user');
                continue;
            }

            // get review user
            $user = $usersData->where('_id', $review['User Name'])->first();

            if ($user) {
                // check if user exists in our database or not
                $authUser = $this->getAuthUser($user);
                if ($authUser) {
                    // user exists in our database
                    $this->info("User exists in our database: " . $user['User name']);
                    $bubbleStore = $storesData->where('_id', $review['Store'])->first(); // buuble store data must have funhub_store_id
                    if (!$bubbleStore) {
                        $this->error("-- Store ID: " . $review['Store'] . " funhub_store_id not found");
                        continue;
                    }

                    $store = Store::where('id', $bubbleStore['funhub_store_id'])->first();
                    if (!$store) {
                        $this->error("-- Store ID: " . $bubbleStore['funhub_store_id'] . " not found in database" . json_encode($review));
                        continue;
                    }

                    $storeRating = StoreRating::where('external_review_id', $review['_id'])->exists();

                    if ($storeRating) {
                        $this->error("-- Store Rating with external_review_id: " . $review['_id'] . " already exists");
                        continue;
                    }

                    if ($store && $authUser && !$storeRating) { // if store exists and authUser exists and storeRating not exists
                        // add store ratings of this user id
                        $rating = $store->storeRatings()->create([
                            'user_id' => $authUser->id,
                            'rating' => $review['Rating'],
                            'comment' => (isset($review['Comments'])) ? $review['Comments'] : null,
                            'external_review_id' => $review['_id'],
                        ]);
                        $this->info("-- Created rating for store: " . $store->id . " and user: " . $authUser->id);

                        if (isset($review['Topic'])) {
                            $categories = $review['Topic']; // topics are rating categories
                            // loop through each category thats available in availableRatingCategories and attach to rating
                            foreach ($categories as $category) {
                                if ($availableRatingCategories->contains('name', $category)) {
                                    $this->info("-- Attaching rating category: " . $category . " to review");
                                    $rating->ratingCategories()->attach($availableRatingCategories->where('name', $category)->first()->id,
                                        ['user_id' => $authUser->id]
                                    );
                                }
                            }
                        }
                    }
                }
            } else {
                // no user found from review data struct
                $this->error("-- User not found from review data: " . json_encode($review));
            }
        } // end of foreach reviewsData

        return Command::SUCCESS;
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
