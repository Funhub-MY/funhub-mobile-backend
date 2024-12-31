<?php

namespace App\Console\Commands;

use App\Models\MerchantOffer;
use App\Models\MerchantOfferCampaign;
use App\Models\MerchantOfferCampaignSchedule;
use App\Models\MerchantOfferVoucher;
use App\Models\MerchantOfferVoucherMovement;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class RedistributeCampaignQuantities extends Command
{
    protected $signature = 'campaign:redistribute-quantities';
    protected $description = 'Redistribute remaining quantities for campaigns that have finished their schedules';

    private const DEFAULT_QUANTITY_PER_SCHEDULE = 5;
    private const DEFAULT_SCHEDULE_DAYS = 3;
    private const DEFAULT_INTERVAL_DAYS = 0;

    private $createdOfferIds = [];

    public function handle()
    {
        $this->info('Starting campaign quantity redistribution...');

        // get campaigns with agreement_quantity set and have schedules that ended
        $campaigns = MerchantOfferCampaign::whereNotNull('agreement_quantity')
            ->whereHas('schedules', function ($query) {
                $query->where('available_until', '<', now());
            })
            ->get();

        foreach ($campaigns as $campaign) {
            $this->processCampaign($campaign);
        }

        $this->info('Campaign quantity redistribution completed.');
    }

    private function processCampaign(MerchantOfferCampaign $campaign)
    {
        // get total sold vouchers
        $soldCount = MerchantOfferVoucher::whereHas('merchant_offer', function ($query) use ($campaign) {
            $query->where('merchant_offer_campaign_id', $campaign->id);
        })->whereNotNull('owned_by_id')->count();

        // calculate remaining quantity to redistribute
        $remainingQuantity = $campaign->agreement_quantity - $soldCount;

        if ($remainingQuantity <= 0) {
            $this->info("Campaign {$campaign->id} has no remaining quantity to redistribute.");
            return;
        }

        // check if all current schedules have ended
        $hasActiveSchedules = $campaign->schedules()
            ->where('available_until', '>', now())
            ->exists();

        if ($hasActiveSchedules) {
            $this->info("Campaign {$campaign->id} still has active schedules.");
            return;
        }

        // get unsold vouchers from ended schedules
        $unsoldVouchers = MerchantOfferVoucher::whereHas('merchant_offer', function ($query) use ($campaign) {
            $query->where('merchant_offer_campaign_id', $campaign->id);
        })->whereDoesntHave('owner')
          ->whereHas('merchant_offer.campaign.schedules', function ($q) {
                $q->where('available_until', '<', now());
          })->get();

        if ($unsoldVouchers->isEmpty()) {
            $this->info("No unsold vouchers found for campaign {$campaign->id}");
            return;
        }

        $this->info("Found {$unsoldVouchers->count()} unsold vouchers for campaign {$campaign->id}");

        // calculate number of schedules needed
        $numberOfSchedules = ceil($unsoldVouchers->count() / self::DEFAULT_QUANTITY_PER_SCHEDULE);
        
        // start from tomorrow
        $startDate = now()->addDay()->startOfDay();

        DB::transaction(function () use ($campaign, $numberOfSchedules, $unsoldVouchers, $startDate) {
            $voucherIndex = 0;

            for ($i = 0; $i < $numberOfSchedules; $i++) {
                $availableAt = $startDate->copy()->addDays($i * (self::DEFAULT_SCHEDULE_DAYS + self::DEFAULT_INTERVAL_DAYS))->addDay()->startOfDay();
                $availableUntil = $availableAt->copy()->addDays(self::DEFAULT_SCHEDULE_DAYS)->subDay()->endOfDay();

                // calculate quantity for this schedule
                $remainingVouchers = $unsoldVouchers->count() - $voucherIndex;
                $scheduleQuantity = min(self::DEFAULT_QUANTITY_PER_SCHEDULE, $remainingVouchers);

                // create new schedule
                $schedule = $campaign->schedules()->create([
                    'available_at' => $availableAt,
                    'available_until' => $availableUntil,
                    'quantity' => $scheduleQuantity,
                    'flash_deal' => $campaign->flash_deal ?? 0,
                    'expiry_days' => $campaign->expiry_days,
                    'user_id' => $campaign->user_id,
                    'publish_at' => $availableAt,
                    'status' => MerchantOfferCampaignSchedule::STATUS_DRAFT
                ]);

                // create merchant offer for the schedule
                $newOffer = $this->createMerchantOffer($campaign, $schedule, $scheduleQuantity);

                // move vouchers to the new offer
                for ($j = 0; $j < $scheduleQuantity; $j++) {
                    $voucher = $unsoldVouchers[$voucherIndex];
                    
                    // double check to ensure voucher is not sold
                    if ($voucher->owned_by_id !== null) {
                        $this->info("Skipping voucher {$voucher->id} as it is already sold");
                        continue;
                    }

                    $oldOffer = $voucher->merchant_offer;

                    // update voucher's offer
                    $voucher->update(['merchant_offer_id' => $newOffer->id]);

                    // record the movement
                    MerchantOfferVoucherMovement::create([
                        'from_merchant_offer_id' => $oldOffer->id,
                        'to_merchant_offer_id' => $newOffer->id,
                        'voucher_id' => $voucher->id,
                        'user_id' => $campaign->user_id,
                    ]);

                    $voucherIndex++;
                }
            }
        });

        $this->info("Successfully redistributed vouchers for campaign {$campaign->id}");
    }

    private function createMerchantOffer($campaign, $schedule, $quantity, $publishAt = null)
    {
        $publishAt = $publishAt ?? now()->addDay()->startOfDay();

        $newOffer = MerchantOffer::create([
            'user_id' => $campaign->user_id,
            'store_id' => $campaign->store_id ?? null,
            'merchant_offer_campaign_id' => $campaign->id,
            'schedule_id' => $schedule->id,
            'name' => $campaign->name,
            'quantity' => $quantity,
            'description' => $campaign->description,
            'sku' => $campaign->sku . '-' . $schedule->id,
            'available_at' => $schedule->available_at,
            'available_until' => $schedule->available_until,
            'publish_at' => $publishAt,
            'flash_deal' => $campaign->flash_deal ?? 0,
            'expiry_days' => $campaign->expiry_days,
            'status' => MerchantOffer::STATUS_DRAFT,
            'unit_price' => $campaign->unit_price,
            'discounted_point_fiat_price' => $campaign->discounted_point_fiat_price,
            'point_fiat_price' => $campaign->point_fiat_price,
            'discounted_fiat_price' => $campaign->discounted_fiat_price,
            'fiat_price' => $campaign->fiat_price,
            'fine_print' => $campaign->fine_print,
            'redemption_policy' => $campaign->redemption_policy,
            'cancellation_policy' => $campaign->cancellation_policy,
            'purchase_method' => $campaign->purchase_method,
        ]);

        $this->createdOfferIds[] = $newOffer->id;

        // copy media and sync relations
        $this->copyMediaAndSyncRelations($campaign, $newOffer);

        return $newOffer;
    }

    private function copyMediaAndSyncRelations($campaign, $newOffer)
    {
        // copy media files
        foreach ($campaign->getMedia() as $media) {
            $media->copy($newOffer);
        }

        // sync any other relations if needed
        // for example: categories, tags, etc.
        if (method_exists($campaign, 'categories')) {
            $newOffer->categories()->sync($campaign->categories()->pluck('id'));
        }

        if (method_exists($campaign, 'tags')) {
            $newOffer->tags()->sync($campaign->tags()->pluck('id'));
        }
    }
}
