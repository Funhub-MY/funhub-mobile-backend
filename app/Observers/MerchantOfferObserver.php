<?php

namespace App\Observers;

use Exception;
use App\Models\MerchantOffer;
use Illuminate\Support\Facades\Log;

class MerchantOfferObserver
{
    // on created or updated
    public function created(MerchantOffer $merchantOffer)
    {
        $this->updateStoreSearchIndex($merchantOffer);
    }

    public function updated(MerchantOffer $merchantOffer)
    {
        $this->updateStoreSearchIndex($merchantOffer);
    }

    private function updateStoreSearchIndex(MerchantOffer $merchantOffer)
    {
        try {
            foreach ($merchantOffer->stores as $store) {
                $store->touch();
            }
        } catch (Exception $e) {
            // log error
            Log::error('[MerchantOfferObserver] Error updating store search index for merchant offer: ' . $merchantOffer->id);
        }
    }
}
