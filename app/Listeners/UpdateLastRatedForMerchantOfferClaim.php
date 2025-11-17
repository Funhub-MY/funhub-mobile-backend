<?php

namespace App\Listeners;

use Exception;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;

class UpdateLastRatedForMerchantOfferClaim implements ShouldQueue
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
        $store = $event->store;
        $user = $event->user;

       try {
            // find all merchant ofers under store
            $merchantOffers = $store->merchant_offers()->whereHas('claims', function ($query) use ($user) {
                $query->where('merchant_offer_user.user_id', $user->id);
            })
            ->get();

            foreach ($merchantOffers as $merchantOffer) {
                $merchantOffer->claims()->where('merchant_offer_user.user_id', $user->id)
                    ->update(['last_rated_at' => now()]);
            }

            Log::info('Merchant offer claim last rated updated for store id: ' . $store->id . ' and user id: ' . $user->id, [
                'store_id' => $store->id,
                'user_id' => $user->id,
            ]);
       } catch (Exception $e) {
           // Log the error
           Log::error('Error updating last rated at for merchant offer claim', [
               'store_id' => $store->id,
               'user_id' => $user->id,
               'error' => $e->getMessage(),
           ]);
       }
    }
}
