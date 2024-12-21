<?php

namespace App\Console\Commands;

use App\Jobs\CreateMerchantOfferJob;
use App\Models\MerchantOffer;
use App\Models\MerchantOfferCampaign;
use App\Models\MerchantOfferSchedule;
use App\Models\MerchantOfferVoucherMovement;
use App\Models\MerchantOfferCampaignSchedule;
use App\Models\MerchantOfferVoucher;
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
                          {schedule_days : Number of days each schedule lasts}
                          {interval_days : Gap in days between schedules}';

    protected $description = 'Update campaign schedules with new quantity and intervals';

    private $createdOfferIds = [];
    private $movedVouchersCount = 0;

    public function handle()
    {
        $campaignId = $this->argument('campaign_id');
        $totalNewQuantity = (int)$this->argument('total_new_quantity');
        $totalMoveQuantity = (int)$this->argument('total_move_quantity');
        $quantityPerSchedule = (int)$this->argument('quantity_per_schedule');
        $scheduleDays = (int)$this->argument('schedule_days');
        $intervalDays = (int)$this->argument('interval_days');

        // find the campaign
        $campaign = MerchantOfferCampaign::findOrFail($campaignId);
        $this->info("Updating schedules for campaign: " . $campaign->name);

        // get unsold vouchers if we need to move any
        $unsoldVouchers = collect();
        if ($totalMoveQuantity > 0) {
            $unsoldVouchers = DB::table('merchant_offer_vouchers')
                ->join('merchant_offers', 'merchant_offers.id', '=', 'merchant_offer_vouchers.merchant_offer_id')
                ->whereNull('merchant_offer_vouchers.owned_by_id')
                ->where('merchant_offers.merchant_offer_campaign_id', $campaign->id)
                ->select('merchant_offer_vouchers.*')
                ->get();

            $availableVouchersCount = $unsoldVouchers->count();
            $this->info("Found " . $availableVouchersCount . " unsold vouchers");

            if ($availableVouchersCount < $totalMoveQuantity) {
                $this->info("Not enough vouchers to move. Adding difference to total_new_quantity");
                $totalNewQuantity += ($totalMoveQuantity - $availableVouchersCount);
                $totalMoveQuantity = $availableVouchersCount;
            }
        }

        // calculate total schedules needed
        $totalQuantity = $totalMoveQuantity + $totalNewQuantity;
        $numberOfSchedules = ceil($totalQuantity / $quantityPerSchedule);
        $this->info("Creating " . $numberOfSchedules . " schedules for total " . $totalQuantity . " vouchers");

        // start from tomorrow at start of day
        $startDate = now()->addDay()->startOfDay();

        $movedCount = 0;
        $newCount = 0;

        for ($i = 0; $i < $numberOfSchedules; $i++) {
            // calculate dates for this schedule
            $availableAt = $startDate->copy();
            $availableUntil = $availableAt->copy()->addDays($scheduleDays)->endOfDay();

            // calculate quantity for this schedule
            $remainingTotal = $totalQuantity - ($i * $quantityPerSchedule);
            $scheduleQuantity = min($quantityPerSchedule, $remainingTotal);

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

            $newOffer = $this->createMerchantOffer($campaign, $schedule, $scheduleQuantity, $availableAt);

            // calculate how many vouchers to move for this schedule
            $moveQuantity = 0;
            if ($movedCount < $totalMoveQuantity) {
                $moveQuantity = min($scheduleQuantity, $totalMoveQuantity - $movedCount);
            }

            if ($moveQuantity > 0) {
                $vouchersToMove = $unsoldVouchers->slice($movedCount, $moveQuantity);
                foreach ($vouchersToMove as $voucher) {
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
                $this->info("Moved " . $vouchersToMove->count() . " vouchers to schedule " . ($i + 1));
            }


            // Generate new vouchers for remaining quantity
            $newQuantity = $scheduleQuantity - $moveQuantity;
            if ($newQuantity > 0) {
                // create vouchers
                $voucherData = [];
                for ($i = 0; $i < $newQuantity; $i++) {
                    $voucherData[] = [
                        'merchant_offer_id' => $newOffer->id,
                        'code' => MerchantOfferVoucher::generateCode(),
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                }
                MerchantOfferVoucher::insert($voucherData);
                $newCount += $newQuantity;
                $this->info("Created " . $newQuantity . " new vouchers for schedule " . ($i + 1));
            }

            $this->info("Schedule " . ($i + 1) . "/" . $numberOfSchedules .
                       " - Available from " . $availableAt . " to " . $availableUntil .
                       " with " . $moveQuantity . " moved and " . $newQuantity . " new vouchers");

            // move startDate for next schedule
            $startDate->addDays($scheduleDays + $intervalDays);
        }

        $this->movedVouchersCount = $movedCount;
        $this->info("Total moved: " . $movedCount . " vouchers");
        $this->info("Total new: " . $newCount . " vouchers");

        // log CSV data
        $this->logCsvData($campaign, $totalNewQuantity, $totalMoveQuantity, $scheduleDays, $intervalDays, $quantityPerSchedule);

        $this->info('Successfully updated campaign schedules!');
    }

    private function logCsvData($campaign, $totalNewQuantity, $totalMoveQuantity, $scheduleDays, $intervalDays, $quantityPerSchedule)
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
            $scheduleDays,
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
            'schedule_days',
            'interval_days',
            'quantity_per_schedule',
            'all_offers_id'
        ]);

        Log::info("Campaign Schedule Update Summary");
        Log::info($header);
        Log::info($csvLine);
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
