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

        // Get all offers for this campaign
        $offers = MerchantOffer::where('merchant_offer_campaign_id', $this->campaign->id)->get();
        
        // Get campaign categories once to avoid repeated queries
        $campaignCategoryIds = $this->campaign->allOfferCategories->pluck('id');
        
        $count = 0;
        foreach ($offers as $offer) {
            // Clear all existing categories
            $offer->allOfferCategories()->detach();
            
            // Sync with campaign categories
            $offer->allOfferCategories()->sync($campaignCategoryIds);
            
            $count++;
        }

        Log::info("[SyncMerchantOfferCategories] Completed", [
            'campaign_id' => $this->campaign->id,
            'offers_updated' => $count,
            'categories_synced' => count($campaignCategoryIds)
        ]);
    }
}
