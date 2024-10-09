<?php

namespace App\Listeners;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use App\Models\Store;
use App\Models\User;
use App\Models\Interaction;
use App\Models\MerchantOffer;
use App\Notifications\NewMerchantOfferListed;
use Illuminate\Support\Facades\Log;

class MerchantOfferPublishedListener
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
       try {
            $offer = $event->merchantOffer;

            if ($offer->status != MerchantOffer::STATUS_PUBLISHED || get_class($offer) != MerchantOffer::class) {
                Log::info('[MerchantOfferPublishedListener] Merchant offer not published or not MerchantOffer instance, skip');
                return;
            }

            // fire new offer listed notification
            // get all users who has added interaction bookmark to related merchant offer stores get notified
            $merchantOfferStores = $offer->stores()->get();
            $interactions = Interaction::where('interactable_type', Store::class)
                ->whereIn('interactable_id', $merchantOfferStores->pluck('id'))
                ->where('type', Interaction::TYPE_BOOKMARK)
                ->whereHas('user', function ($query) {
                    $query->where('status', User::STATUS_ACTIVE);
                })
                ->get();

            foreach ($interactions as $interaction) {
                $user = $interaction->user;
                try {
                    $locale = $user->last_lang ?? config('app.locale');
                    $user->notify((new NewMerchantOfferListed($offer))->locale($locale));
                } catch (\Exception $e) {
                    Log::error('[PublishMerchantOffer] Notification error when bookmarked merchant offer published: '.$offer->id.' to user: '.$user->id);
                }
            }
       } catch (\Exception $e) {
           Log::error('[MerchantOfferPublishedListener] Error when bookmarked merchant offer published', [
               'error' => $e->getMessage(),
           ]);
       }
    }
}
