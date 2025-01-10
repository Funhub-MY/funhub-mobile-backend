<?php

namespace App\Jobs;

use App\Models\MerchantOffer;
use App\Models\MerchantOfferCampaign;
use App\Models\MerchantOfferVoucher;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class CreateMerchantOfferJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $campaignId;
    protected $scheduleId;

    public function __construct($campaignId, $scheduleId)
    {
        $this->campaignId = $campaignId;
        $this->scheduleId = $scheduleId;
    }

    public function handle()
    {
        $campaign = MerchantOfferCampaign::findOrFail($this->campaignId);
        $schedule = $campaign->schedules()->findOrFail($this->scheduleId);

        $offer = MerchantOffer::create([
            'user_id' => $campaign->user_id,
            'store_id' => $campaign->store_id ?? null,
            'merchant_offer_campaign_id' => $campaign->id,
            'schedule_id' => $schedule->id,
            'name' => $campaign->name,
			'highlight_messages' => $campaign->highlight_messages ? json_encode($campaign->highlight_messages) : null,
            'description' => $campaign->description,
            'sku' => $campaign->sku . '-' . $schedule->id,
            'available_for_web' => $campaign->available_for_web,
            'fine_print' => $campaign->fine_print,
            'redemption_policy' => $campaign->redemption_policy,
            'cancellation_policy' => $campaign->cancellation_policy,
            'publish_at' => $schedule->publish_at,
            'purchase_method' => $campaign->purchase_method,
            'unit_price' => $campaign->unit_price,
            'discounted_point_fiat_price' => $campaign->discounted_point_fiat_price,
            'point_fiat_price' => $campaign->point_fiat_price,
            'discounted_fiat_price' => $campaign->discounted_fiat_price,
            'fiat_price' => $campaign->fiat_price,
            'expiry_days' => ($schedule->expiry_days ?? $campaign->expiry_days),
            'available_at' => $schedule->available_at,
            'available_until' => $schedule->available_until,
            'quantity' => $schedule->quantity,
            'status' => $schedule->status,
        ]);

        // Copy media
        $mediaItems = $campaign->getMedia(MerchantOfferCampaign::MEDIA_COLLECTION_NAME);
        foreach ($mediaItems as $mediaItem) {
            $mediaItem->copy($offer, MerchantOffer::MEDIA_COLLECTION_NAME);
        }

        $mediaItems = $campaign->getMedia(MerchantOfferCampaign::MEDIA_COLLECTION_HORIZONTAL_BANNER);
        foreach ($mediaItems as $mediaItem) {
            $mediaItem->copy($offer, MerchantOffer::MEDIA_COLLECTION_HORIZONTAL_BANNER);
        }

        // Sync categories and stores
        $offer->allOfferCategories()->sync($campaign->allOfferCategories->pluck('id'));
        $offer->stores()->sync($campaign->stores->pluck('id'));

        // Create vouchers
        $voucherData = [];
        for ($i = 0; $i < $schedule->quantity; $i++) {
            $voucherData[] = [
                'merchant_offer_id' => $offer->id,
                'code' => MerchantOfferVoucher::generateCode(),
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }
        MerchantOfferVoucher::insert($voucherData);

        // sync algolia
        try {
            $offer->refresh();
            $offer->searchable();
        } catch (\Exception $e) {
            Log::error('[CreateMerchantOfferJob] Error syncing algolia', [
                'error' => $e->getMessage()
            ]);
        }
    }
}
