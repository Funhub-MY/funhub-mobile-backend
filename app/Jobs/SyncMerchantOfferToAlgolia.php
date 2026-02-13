<?php

namespace App\Jobs;

use App\Models\MerchantOffer;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SyncMerchantOfferToAlgolia implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $campaignId;

    /**
     * Create a new job instance.
     *
     * @param int $campaignId
     * @return void
     */
    public function __construct(int $campaignId)
    {
        $this->campaignId = $campaignId;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        Log::info("[SyncMerchantOfferToAlgolia] Starting", [
            'campaign_id' => $this->campaignId
        ]);

        $successCount = 0;
        $errorCount = 0;

        // Use chunkById to avoid loading all offers into memory
        MerchantOffer::where('merchant_offer_campaign_id', $this->campaignId)
            ->chunkById(50, function ($offers) use (&$successCount, &$errorCount) {
                foreach ($offers as $offer) {
                    try {
                        $offer->searchable();
                        $successCount++;
                    } catch (\Exception $e) {
                        $errorCount++;
                        Log::error('[SyncMerchantOfferToAlgolia] Error syncing offer to Algolia', [
                            'offer_id' => $offer->id,
                            'error' => $e->getMessage()
                        ]);
                    }
                }
            });

        Log::info("[SyncMerchantOfferToAlgolia] Completed", [
            'campaign_id' => $this->campaignId,
            'offers_synced' => $successCount,
            'offers_failed' => $errorCount
        ]);
    }
}
