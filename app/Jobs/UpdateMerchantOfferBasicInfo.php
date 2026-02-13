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

class UpdateMerchantOfferBasicInfo implements ShouldQueue
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
        Log::info("[UpdateMerchantOfferBasicInfo] Starting", [
            'campaign_id' => $this->campaign->id,
            'campaign_name' => $this->campaign->name
        ]);

        $count = 0;
        $campaign = $this->campaign;

        MerchantOffer::where('merchant_offer_campaign_id', $campaign->id)
            ->chunkById(100, function ($offers) use ($campaign, &$count) {
                foreach ($offers as $offer) {
                    $isArchived = $offer->status === MerchantOffer::STATUS_ARCHIVED;

                    // Sync campaign fields to offer, but do NOT overwrite description/name/fine_print
                    // so that per-offer edits in Merchant Offer listing/edit are preserved.
                    $offer->update([
                        'name' => $campaign->name,
                        'highlight_messages' => $campaign->highlight_messages,
                        'available_for_web' => $campaign->available_for_web,
                        'redemption_policy' => $campaign->redemption_policy,
                        'cancellation_policy' => $campaign->cancellation_policy,
                        'purchase_method' => $campaign->purchase_method,
                        'unit_price' => $campaign->unit_price,
                        'discounted_point_fiat_price' => $campaign->discounted_point_fiat_price,
                        'point_fiat_price' => $campaign->point_fiat_price,
                        'discounted_fiat_price' => $campaign->discounted_fiat_price,
                        'fiat_price' => $campaign->fiat_price,
                        'expiry_days' => $campaign->expiry_days,
                        'user_id' => $campaign->user_id,
                    ]);

                    if ($isArchived) {
                        $offer->update(['status' => MerchantOffer::STATUS_ARCHIVED]);
                    }

                    $count++;
                }
            });

        Log::info("[UpdateMerchantOfferBasicInfo] Completed", [
            'campaign_id' => $campaign->id,
            'offers_updated' => $count
        ]);
    }
}
