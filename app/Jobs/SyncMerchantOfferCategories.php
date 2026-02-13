<?php

namespace App\Jobs;

use App\Models\MerchantOffer;
use App\Models\MerchantOfferCampaign;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SyncMerchantOfferCategories implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $campaign;

    /**
     * Create a new job instance.
     *
     * @param MerchantOfferCampaign $campaign
     * @return void
     */
    public function __construct(MerchantOfferCampaign $campaign)
    {
        $this->campaign = $campaign;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        Log::info("[SyncMerchantOfferCategories] Starting", [
            'campaign_id' => $this->campaign->id,
            'campaign_name' => $this->campaign->name
        ]);

        $campaignCategoryIds = $this->campaign->allOfferCategories->pluck('id');
        $count = 0;

        MerchantOffer::where('merchant_offer_campaign_id', $this->campaign->id)
            ->chunkById(100, function ($offers) use ($campaignCategoryIds, &$count) {
                foreach ($offers as $offer) {
                    $offer->allOfferCategories()->detach();
                    $offer->allOfferCategories()->sync($campaignCategoryIds);
                    $count++;
                }
            });

        Log::info("[SyncMerchantOfferCategories] Completed", [
            'campaign_id' => $this->campaign->id,
            'offers_updated' => $count,
            'categories_synced' => count($campaignCategoryIds)
        ]);
    }
}
