<?php

namespace App\Jobs;

use App\Models\MerchantOffer;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SyncMerchantOfferStores implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $offerId;
    protected $storeIds;

    /**
     * Create a new job instance.
     *
     * @param int $offerId
     * @param array $storeIds
     * @return void
     */
    public function __construct($offerId, array $storeIds)
    {
        $this->offerId = $offerId;
        $this->storeIds = $storeIds;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        try {
            $offer = MerchantOffer::find($this->offerId);
            
            if (!$offer) {
                Log::error('[SyncMerchantOfferStores] Offer not found', [
                    'offer_id' => $this->offerId,
                ]);
                return;
            }

            // Clear and sync stores in a single operation
            $offer->stores()->sync($this->storeIds);
            
            Log::info('[SyncMerchantOfferStores] Successfully synced stores for offer', [
                'offer_id' => $this->offerId,
                'store_count' => count($this->storeIds),
            ]);
        } catch (\Exception $e) {
            Log::error('[SyncMerchantOfferStores] Error syncing stores', [
                'offer_id' => $this->offerId,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
