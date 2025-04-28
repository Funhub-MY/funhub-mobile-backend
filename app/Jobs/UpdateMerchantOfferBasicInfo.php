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

        // Get all offers for this campaign
        $offers = MerchantOffer::where('merchant_offer_campaign_id', $this->campaign->id)->get();
        
        $count = 0;
        foreach ($offers as $offer) {
            // Preserve the status if the offer is archived
            $isArchived = $offer->status === MerchantOffer::STATUS_ARCHIVED;

            $offer->update([
                'name' => $this->campaign->name,
                'highlight_messages' => $this->campaign->highlight_messages,
                'description' => $this->campaign->description,
                'fine_print' => $this->campaign->fine_print,
                'available_for_web' => $this->campaign->available_for_web,
                'redemption_policy' => $this->campaign->redemption_policy,
                'cancellation_policy' => $this->campaign->cancellation_policy,
                'purchase_method' => $this->campaign->purchase_method,
                'unit_price' => $this->campaign->unit_price,
                'discounted_point_fiat_price' => $this->campaign->discounted_point_fiat_price,
                'point_fiat_price' => $this->campaign->point_fiat_price,
                'discounted_fiat_price' => $this->campaign->discounted_fiat_price,
                'fiat_price' => $this->campaign->fiat_price,
                'expiry_days' => $this->campaign->expiry_days,
                'user_id' => $this->campaign->user_id,
            ]);

            // If the offer was archived, restore its status to archived
            if ($isArchived) {
                $offer->update(['status' => MerchantOffer::STATUS_ARCHIVED]);
            }
            
            $count++;
        }

        Log::info("[UpdateMerchantOfferBasicInfo] Completed", [
            'campaign_id' => $this->campaign->id,
            'offers_updated' => $count
        ]);
    }
}
