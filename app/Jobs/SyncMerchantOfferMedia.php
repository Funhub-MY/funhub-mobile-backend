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

class SyncMerchantOfferMedia implements ShouldQueue
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
        Log::info("[SyncMerchantOfferMedia] Starting", [
            'campaign_id' => $this->campaign->id,
            'campaign_name' => $this->campaign->name
        ]);

        // Get all offers for this campaign
        $offers = MerchantOffer::where('merchant_offer_campaign_id', $this->campaign->id)->get();
        
        $count = 0;
        foreach ($offers as $offer) {
            // Clear existing media collections
            $offer->clearMediaCollection(MerchantOffer::MEDIA_COLLECTION_NAME);
            $offer->clearMediaCollection(MerchantOffer::MEDIA_COLLECTION_HORIZONTAL_BANNER);

            // Get fresh campaign model to ensure we have the latest media
            $model = MerchantOfferCampaign::find($this->campaign->id);
            
            // Copy standard media items
            $mediaItems = $model->getMedia(MerchantOfferCampaign::MEDIA_COLLECTION_NAME);
            foreach ($mediaItems as $mediaItem) {
                $mediaItem->copy($offer, MerchantOffer::MEDIA_COLLECTION_NAME);
            }

            // Copy horizontal banner media items
            $bannerMediaItems = $model->getMedia(MerchantOfferCampaign::MEDIA_COLLECTION_HORIZONTAL_BANNER);
            foreach ($bannerMediaItems as $mediaItem) {
                $mediaItem->copy($offer, MerchantOffer::MEDIA_COLLECTION_HORIZONTAL_BANNER);
            }
            
            $count++;
        }

        Log::info("[SyncMerchantOfferMedia] Completed", [
            'campaign_id' => $this->campaign->id,
            'offers_updated' => $count,
            'media_items_synced' => count($mediaItems) + count($bannerMediaItems ?? [])
        ]);
    }
}
