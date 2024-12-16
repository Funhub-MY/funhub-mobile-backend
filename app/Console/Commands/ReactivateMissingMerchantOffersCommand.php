<?php

namespace App\Console\Commands;

use App\Models\MerchantOffer;
use App\Models\MerchantOfferCampaign;
use App\Models\MerchantOfferVoucher;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class ReactivateMissingMerchantOffersCommand extends Command
{
    protected $signature = 'merchant-offers:reactivate {campaign_id : The ID of the campaign to check}';
    protected $description = 'Reactivate missing merchant offers for a campaign\'s schedules';

    public function handle(): int
    {
        $campaignId = $this->argument('campaign_id');
        
        try {
            $campaign = MerchantOfferCampaign::findOrFail($campaignId);
            $this->info("Processing campaign: {$campaign->name}");
            
            // Only get schedules that are upcoming (available_until is in the future)
            $schedules = $campaign->schedules()
                ->where('available_until', '>', now())
                ->get();

            Log::info("[ReactivateMissingMerchantOffersCommand] Found " . $schedules->count() . " upcomingschedules for campaign ID: {$campaign->id}");
            $this->info("Found " . $schedules->count() . " upcomingschedules for campaign ID: {$campaign->id}");

            if ($schedules->isEmpty()) {
                $this->info("No upcoming schedules found for this campaign.");
                return self::SUCCESS;
            }
            
            $createdCount = 0;
            
            foreach ($schedules as $schedule) {
                // Check if merchant offer exists for this schedule
                $existingOffer = MerchantOffer::where('merchant_offer_campaign_id', $campaign->id)
                    ->where('schedule_id', $schedule->id)
                    ->first();
                
                if (!$existingOffer) {
                    $this->info("Creating missing merchant offer for schedule ID: {$schedule->id} with available_at: {$schedule->available_at} and available_until: {$schedule->available_until}");
                    $offer = $this->createMerchantOffer($campaign, $schedule);
                    $createdCount++;
                    
                    $this->info("Created merchant offer ID: {$offer->id}");
                }
            }
            
            $this->info("Process completed. Created {$createdCount} missing merchant offers.");
            Log::info("[ReactivateMissingMerchantOffersCommand] Process completed. Created {$createdCount} missing merchant offers.", [
                'created_count' => $createdCount,
                'campaign_id' => $campaignId
            ]);
            return self::SUCCESS;
            
        } catch (\Exception $e) {
            $this->error("Error processing campaign ID {$campaignId}: " . $e->getMessage());
            Log::error("Error in ReactivateMissingMerchantOffersCommand", [
                'campaign_id' => $campaignId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return self::FAILURE;
        }
    }

    protected function createMerchantOffer(MerchantOfferCampaign $campaign, $schedule): MerchantOffer
    {
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

        Log::info("[ReactivateMissingMerchantOffersCommand] Created merchant offer ID: {$offer->id}");

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

        return $offer;
    }
}
