<?php

namespace App\Jobs;

use App\Models\MerchantOffer;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SyncAllMerchantOfferStores implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $campaignId;
    protected $storeIds;

    /**
     * Create a new job instance.
     *
     * @param int $campaignId
     * @param array $storeIds
     * @return void
     */
    public function __construct(int $campaignId, array $storeIds)
    {
        $this->campaignId = $campaignId;
        $this->storeIds = $storeIds;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        Log::info("[SyncAllMerchantOfferStores] Starting", [
            'campaign_id' => $this->campaignId,
            'store_count' => count($this->storeIds)
        ]);

        // Get all offers for this campaign
        $offers = MerchantOffer::where('merchant_offer_campaign_id', $this->campaignId)->get();
        
        $count = 0;
        $batchSize = 50; // Process in smaller batches
        $totalOffers = $offers->count();
        
        // Process offers in batches to avoid memory issues
        foreach ($offers->chunk($batchSize) as $offerChunk) {
            foreach ($offerChunk as $offer) {
                // Dispatch individual store sync job for each offer
                SyncMerchantOfferStores::dispatch($offer->id, $this->storeIds);
                $count++;
            }
            
            // Log progress for large campaigns
            if ($totalOffers > 100) {
                Log::info("[SyncAllMerchantOfferStores] Progress", [
                    'campaign_id' => $this->campaignId,
                    'processed' => $count,
                    'total' => $totalOffers,
                    'percent' => round(($count / $totalOffers) * 100, 2) . '%'
                ]);
            }
        }

        Log::info("[SyncAllMerchantOfferStores] Completed", [
            'campaign_id' => $this->campaignId,
            'offers_processed' => $count
        ]);
    }
}
