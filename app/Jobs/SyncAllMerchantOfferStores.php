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

        $count = 0;
        $totalOffers = MerchantOffer::where('merchant_offer_campaign_id', $this->campaignId)->count();

        // Use cursor to avoid loading all offers into memory
        MerchantOffer::where('merchant_offer_campaign_id', $this->campaignId)
            ->select('id')
            ->cursor()
            ->each(function ($offer) use (&$count, $totalOffers) {
                SyncMerchantOfferStores::dispatch($offer->id, $this->storeIds);
                $count++;

                if ($totalOffers > 100 && $count % 50 === 0) {
                    Log::info("[SyncAllMerchantOfferStores] Progress", [
                        'campaign_id' => $this->campaignId,
                        'processed' => $count,
                        'total' => $totalOffers,
                        'percent' => round(($count / $totalOffers) * 100, 2) . '%'
                    ]);
                }
            });

        Log::info("[SyncAllMerchantOfferStores] Completed", [
            'campaign_id' => $this->campaignId,
            'offers_processed' => $count
        ]);
    }
}
