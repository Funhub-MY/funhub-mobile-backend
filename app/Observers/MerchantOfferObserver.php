<?php

namespace App\Observers;

use App\Models\MerchantOffer;

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
        foreach ($merchantOffer->stores as $store) {
            $store->touch();
        }
    }
}
