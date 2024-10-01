<?php

namespace App\Listeners;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;

class RatedLocationListener implements ShouldQueue
{
    /**
     * Create the event listener.
     *
     * @return void
     */
    public function __construct()
    {
        //
    }

    /**
     * Handle the event.
     *
     * @param  object  $event
     * @return void
     */
    public function handle($event)
    {
        $user = $event->user;
        $location = $event->location;
        $rating = $event->rating;
        $articleId = (isset($event->articleId)) ? $event->articleId : null;

        // if location has a store attach, create a copy rating for the store
        if ($location->stores) {
            foreach ($location->stores as $store) { // each location has many stores
                $storeRating = $store->storeRatings()->create([
                    'user_id' => $user->id,
                    'rating' => $rating,
                    'comment' => null,
                    'article_id' => ($articleId) ? $articleId : null
                ]);

                // update store->ratings avg
                $store->update([
                    'ratings' => $store->storeRatings()->avg('rating')
                ]);

                Log::info('Store rating created', [
                    'store_id' => $store->id,
                    'user_id' => $user->id,
                    'rating' => $rating,
                    'article_id' => $articleId
                ]);
            }
        }
    }
}
