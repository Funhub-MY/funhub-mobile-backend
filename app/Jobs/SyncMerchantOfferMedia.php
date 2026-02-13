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

        $count = 0;
        $mediaItemsSynced = 0;
        $model = MerchantOfferCampaign::find($this->campaign->id);
        $mediaItems = $model->getMedia(MerchantOfferCampaign::MEDIA_COLLECTION_NAME);
        $bannerMediaItems = $model->getMedia(MerchantOfferCampaign::MEDIA_COLLECTION_HORIZONTAL_BANNER);

        MerchantOffer::where('merchant_offer_campaign_id', $this->campaign->id)
            ->chunkById(50, function ($offers) use ($mediaItems, $bannerMediaItems, &$count, &$mediaItemsSynced) {
                foreach ($offers as $offer) {
                    $offer->clearMediaCollection(MerchantOffer::MEDIA_COLLECTION_NAME);
                    $offer->clearMediaCollection(MerchantOffer::MEDIA_COLLECTION_HORIZONTAL_BANNER);

                    foreach ($mediaItems as $mediaItem) {
                        $mediaItem->copy($offer, MerchantOffer::MEDIA_COLLECTION_NAME);
                    }
                    foreach ($bannerMediaItems as $bannerItem) {
                        $bannerItem->copy($offer, MerchantOffer::MEDIA_COLLECTION_HORIZONTAL_BANNER);
                    }

                    $mediaItemsSynced += count($mediaItems) + count($bannerMediaItems);
                    $count++;
                }
            });

        Log::info("[SyncMerchantOfferMedia] Completed", [
            'campaign_id' => $this->campaign->id,
            'offers_updated' => $count,
            'media_items_synced' => $mediaItemsSynced
        ]);
    }
}
