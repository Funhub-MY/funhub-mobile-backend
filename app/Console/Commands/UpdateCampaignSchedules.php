<?php

namespace App\Console\Commands;

use App\Jobs\CreateMerchantOfferJob;
use App\Models\MerchantOffer;
use App\Models\MerchantOfferCampaign;
use App\Models\MerchantOfferSchedule;
use App\Models\MerchantOfferVoucherMovement;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Update campaign schedules
 * Move or create new schedules easily with just providing quantities
 * 
 * Adhoc requested by BD to put up sales for balance unsold vouchers.
 */
class UpdateCampaignSchedules extends Command
{
    protected $signature = 'campaign:update-schedules 
                          {campaign_id : The ID of the campaign to update}
                          {total_new_quantity : Total new quantity to create}
                          {total_move_quantity : Total quantity to move from existing offers}
                          {quantity_per_schedule : Quantity for each schedule}
                          {interval_days : Interval between schedules in days}
                          {total_days : Total number of days for all schedules}';

    protected $description = 'Update campaign schedules with new quantity and intervals';

    private $createdOfferIds = [];
    private $movedVouchersCount = 0;

    public function handle()
    {
        $campaignId = $this->argument('campaign_id');
        $totalNewQuantity = (int)$this->argument('total_new_quantity');
        $totalMoveQuantity = (int)$this->argument('total_move_quantity');
        $quantityPerSchedule = (int)$this->argument('quantity_per_schedule');
        $intervalDays = (int)$this->argument('interval_days');
        $totalDays = (int)$this->argument('total_days');

        // find the campaign
        $campaign = MerchantOfferCampaign::findOrFail($campaignId);
        $this->info("Updating schedules for campaign: " . $campaign->name);

        // handle moving existing vouchers if any
        if ($totalMoveQuantity > 0) {
            $remainingToCreate = $this->handleVoucherMovement($campaign, $totalMoveQuantity, $intervalDays);
            // add any unmoved quantity to total_new_quantity
            $totalNewQuantity += $remainingToCreate;

            if ($remainingToCreate > 0) {
                $this->info('-- has move quantity difference of ' . $remainingToCreate);
                Log::info('-- has move quantity difference of ' . $remainingToCreate);
            }
        }

        // handle new vouchers if any
        if ($totalNewQuantity > 0) {
            $this->handleNewVouchers($campaign, $totalNewQuantity, $quantityPerSchedule, $intervalDays, $totalDays);
        }

        // log CSV data
        $this->logCsvData($campaign, $totalNewQuantity, $totalMoveQuantity, $intervalDays, $quantityPerSchedule);

        $this->info('Successfully updated campaign schedules!');
    }

    private function logCsvData($campaign, $totalNewQuantity, $totalMoveQuantity, $intervalDays, $quantityPerSchedule)
    {
        // get all created offer IDs as pipe-separated string
        $offerIds = implode('|', $this->createdOfferIds);

        // create CSV line
        $csvLine = implode(',', [
            $campaign->sku,
            $campaign->id,
            $totalNewQuantity,
            $totalMoveQuantity,
            count($this->createdOfferIds),
            $this->movedVouchersCount,
            $intervalDays,
            $quantityPerSchedule,
            $offerIds
        ]);

        // Add header if this is first run
        $header = implode(',', [
            'campaign_sku',
            'campaign_id',
            'total_new_quantity',
            'total_move_quantity',
            'new_offers_created_count',
            'total_moved_of_unsold_vouchers',
            'interval',
            'quantity_per_schedule',
            'all_offers_id'
        ]);

        Log::info("Campaign Schedule Update Summary");
        Log::info($header);
        Log::info($csvLine);
    }

    private function handleVoucherMovement($campaign, $totalMoveQuantity, $intervalDays)
    {
        // get all unsold vouchers from campaign's offers
        $unsoldVouchers = DB::table('merchant_offer_vouchers')
            ->join('merchant_offers', 'merchant_offers.id', '=', 'merchant_offer_vouchers.merchant_offer_id')
            ->whereNull('merchant_offer_vouchers.owned_by_id')
            ->where('merchant_offers.merchant_offer_campaign_id', $campaign->id)
            ->select('merchant_offer_vouchers.*')
            ->get();

        $availableVouchersCount = $unsoldVouchers->count();
        $this->info("Found " . $availableVouchersCount . " unsold vouchers");

        if ($availableVouchersCount == 0) {
            $this->info("No vouchers available to move, adding to total_new_quantity");
            return $totalMoveQuantity;
        }

        // create new schedule and offer for moved vouchers
        $schedule = $campaign->schedules()->create([
            'available_at' => now(),
            'available_until' => now()->addDays($intervalDays),
            'quantity' => min($totalMoveQuantity, $availableVouchersCount),
            'flash_deal' => $campaign->flash_deal,
            'expiry_days' => $campaign->expiry_days,
            'user_id' => $campaign->user_id,
        ]);

        // create merchant offer manually (don't use job as we don't want vouchers created)
        $newOffer = $this->createMerchantOffer($campaign, $schedule);

        // move vouchers
        $movedCount = 0;
        foreach ($unsoldVouchers as $voucher) {
            if ($movedCount >= $totalMoveQuantity) break;

            DB::transaction(function () use ($voucher, $newOffer, $campaign) {
                // update voucher
                DB::table('merchant_offer_vouchers')
                    ->where('id', $voucher->id)
                    ->update(['merchant_offer_id' => $newOffer->id]);

                // record movement
                MerchantOfferVoucherMovement::create([
                    'from_merchant_offer_id' => $voucher->merchant_offer_id,
                    'to_merchant_offer_id' => $newOffer->id,
                    'voucher_id' => $voucher->id,
                    'user_id' => $campaign->user_id,
                ]);
            });

            $movedCount++;
        }

        // track moved vouchers count
        $this->movedVouchersCount = $movedCount;

        $this->info("Moved " . $movedCount . " vouchers to new offer");

        // if we couldn't move all requested vouchers, return the difference
        return max(0, $totalMoveQuantity - $movedCount);
    }

    private function handleNewVouchers($campaign, $totalNewQuantity, $quantityPerSchedule, $intervalDays, $totalDays)
    {
        // if total quantity is less than quantity per schedule, create just one schedule
        if ($totalNewQuantity <= $quantityPerSchedule) {
            $this->createSchedule($campaign, $totalNewQuantity, now(), now()->addDays($intervalDays), 1, 1);
            return;
        }

        // calculate number of schedules needed based on total quantity and quantity per schedule
        $numberOfSchedules = ceil($totalNewQuantity / $quantityPerSchedule);
        
        // ensure we don't exceed total days
        $maxPossibleSchedules = ceil($totalDays / $intervalDays);
        $numberOfSchedules = min($numberOfSchedules, $maxPossibleSchedules);

        $this->info("Creating " . $numberOfSchedules . " schedules with " . $quantityPerSchedule . " quantity each");

        // start from now
        $startDate = now();

        // create schedules
        for ($i = 0; $i < $numberOfSchedules; $i++) {
            $availableAt = $startDate->copy()->addDays($i * $intervalDays);
            $availableUntil = $availableAt->copy()->addDays($intervalDays);

            // for the last schedule, adjust quantity if there's a remainder
            $quantity = $quantityPerSchedule;
            if ($i == $numberOfSchedules - 1) {
                $remainingQuantity = $totalNewQuantity - ($quantityPerSchedule * ($numberOfSchedules - 1));
                $quantity = min($quantityPerSchedule, $remainingQuantity);
            }

            $this->createSchedule($campaign, $quantity, $availableAt, $availableUntil, $i + 1, $numberOfSchedules);
        }
    }

    private function createSchedule($campaign, $quantity, $availableAt, $availableUntil, $currentSchedule, $totalSchedules)
    {
        $schedule = $campaign->schedules()->create([
            'quantity' => $quantity,
            'available_at' => $availableAt,
            'available_until' => $availableUntil,
        ]);

        // create merchant offer directly instead of using job
        $newOffer = $this->createMerchantOffer($campaign, $schedule);

        $this->info("Created schedule " . $currentSchedule . "/" . $totalSchedules . " - Available from " . $availableAt . " to " . $availableUntil . " with quantity " . $quantity);

        return $schedule;
    }

    private function createMerchantOffer($campaign, $schedule)
    {
        $newOffer = MerchantOffer::create([
            'user_id' => $campaign->user_id,
            'store_id' => $campaign->store_id ?? null,
            'merchant_offer_campaign_id' => $campaign->id,
            'schedule_id' => $schedule->id,
            'name' => $campaign->name,
            'description' => $campaign->description,
            'sku' => $campaign->sku . '-' . $schedule->id,
            'available_at' => $schedule->available_at,
            'available_until' => $schedule->available_until,
            'flash_deal' => $campaign->flash_deal,
            'expiry_days' => $campaign->expiry_days,
            'status' => MerchantOffer::STATUS_PUBLISHED,
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
        // copy media
        $mediaItems = $campaign->getMedia(MerchantOfferCampaign::MEDIA_COLLECTION_NAME);
        foreach ($mediaItems as $mediaItem) {
            $mediaItem->copy($newOffer, MerchantOffer::MEDIA_COLLECTION_NAME);
        }

        $mediaItems = $campaign->getMedia(MerchantOfferCampaign::MEDIA_COLLECTION_HORIZONTAL_BANNER);
        foreach ($mediaItems as $mediaItem) {
            $mediaItem->copy($newOffer, MerchantOffer::MEDIA_COLLECTION_HORIZONTAL_BANNER);
        }

        // sync categories and stores
        $newOffer->allOfferCategories()->sync($campaign->allOfferCategories->pluck('id'));
        $newOffer->stores()->sync($campaign->stores->pluck('id'));
    }
}
