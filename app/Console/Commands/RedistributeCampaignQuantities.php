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
    protected $signature = 'campaign:redistribute-quantities {--dry-run : Run in dry run mode without making actual changes}';
    protected $description = 'Redistribute remaining quantities for campaigns that have finished their schedules';

    private const DEFAULT_QUANTITY_PER_SCHEDULE = 5;
    private const DEFAULT_SCHEDULE_DAYS = 3;
    private const DEFAULT_INTERVAL_DAYS = 0;

    private $createdOfferIds = [];
    private $isDryRun = false;

    public function handle()
    {
        $this->isDryRun = $this->option('dry-run');
        if ($this->isDryRun) {
            $this->info('Running in DRY RUN mode - no actual changes will be made');
            $this->newLine();
        }

        $this->info('Starting campaign quantity redistribution...');

        // get campaigns with agreement_quantity set and have schedules that ended
        $campaigns = MerchantOfferCampaign::whereNotNull('agreement_quantity')
            ->where('auto_move_vouchers', true)
            ->whereHas('schedules', function ($query) {
                $query->where('available_until', '<', now())
                    ->whereNotExists(function ($q) {
                        $q->from('merchant_offer_campaigns_schedules as s2')
                            ->whereRaw('s2.merchant_offer_campaign_id = merchant_offer_campaigns_schedules.merchant_offer_campaign_id')
                            ->where('s2.available_until', '>', now());
                    });
            })
            ->get();

        if ($campaigns->isEmpty()) {
            $this->info('No campaigns found with ended schedules that has agreement_quantity set & auto move vouchers turned on.');
            return;
        }

        $this->info(sprintf('Found %d campaigns to process', $campaigns->count()));
        $this->newLine();

        foreach ($campaigns as $campaign) {
            $this->processCampaign($campaign);
            $this->newLine();
        }

        $this->info('Campaign quantity redistribution completed.');
        if ($this->isDryRun) {
            $this->warn('This was a dry run - no actual changes were made');
        }
    }

    private function processCampaign(MerchantOfferCampaign $campaign)
    {
        $this->info("Processing Campaign ID: {$campaign->id} - {$campaign->name}");
        $this->line("----------------------------------------");

        // get total sold vouchers
        $soldCount = MerchantOfferVoucher::whereHas('merchant_offer', function ($query) use ($campaign) {
            $query->where('merchant_offer_campaign_id', $campaign->id);
        })->whereNotNull('owned_by_id')->count();

        // calculate remaining quantity to redistribute
        $remainingQuantity = $campaign->agreement_quantity - $soldCount;

        $this->line(sprintf('Agreement Quantity: %d', $campaign->agreement_quantity));
        $this->line(sprintf('Sold Vouchers: %d', $soldCount));
        $this->line(sprintf('Remaining Quantity: %d', $remainingQuantity));

        if ($remainingQuantity <= 0) {
            $this->warn("Campaign has no remaining quantity to redistribute.");
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
            $this->warn("No unsold vouchers found for this campaign");
            return;
        }

        $this->info(sprintf("Found %d unsold vouchers to redistribute", $unsoldVouchers->count()));

        // calculate number of schedules needed
        $numberOfSchedules = ceil($unsoldVouchers->count() / self::DEFAULT_QUANTITY_PER_SCHEDULE);
        $this->line(sprintf('Will create %d new schedules', $numberOfSchedules));
        
        // start from today
        $startDate = now()->startOfDay();

        if (!$this->isDryRun) {
            DB::transaction(function () use ($campaign, $numberOfSchedules, $unsoldVouchers, $startDate) {
                $voucherIndex = 0;

                for ($i = 0; $i < $numberOfSchedules; $i++) {
                    
                    $availableAt = $startDate->copy()->addDays($i * (self::DEFAULT_SCHEDULE_DAYS + self::DEFAULT_INTERVAL_DAYS))->startOfDay();
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

                    Log::info("[RedistributeCampaignQuantities] Creating schedule for campaign ID: {$campaign->id}", [
                        "available_at" => $schedule->available_at,
                        "available_until" => $schedule->available_until
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

                    Log::info('[RedistributeCampaignQuantities] Created new offer: ' . $newOffer->id);
                }
            });

            $this->info("Successfully redistributed vouchers for campaign {$campaign->id}");
        } else {
            // Dry run simulation
            $voucherIndex = 0;
            for ($i = 0; $i < $numberOfSchedules; $i++) {
                $availableAt = $startDate->copy()->addDays($i * (self::DEFAULT_SCHEDULE_DAYS + self::DEFAULT_INTERVAL_DAYS))->startOfDay();
                $availableUntil = $availableAt->copy()->addDays(self::DEFAULT_SCHEDULE_DAYS)->subDay()->endOfDay();
                
                $remainingVouchers = $unsoldVouchers->count() - $voucherIndex;
                $scheduleQuantity = min(self::DEFAULT_QUANTITY_PER_SCHEDULE, $remainingVouchers);

                $this->info(sprintf("\nSimulating Schedule #%d:", $i + 1));
                $this->line(sprintf('Available From: %s', $availableAt->format('Y-m-d H:i:s')));
                $this->line(sprintf('Available Until: %s', $availableUntil->format('Y-m-d H:i:s')));
                $this->line(sprintf('Quantity: %d vouchers', $scheduleQuantity));
                
                $this->line('Vouchers to be moved:');
                for ($j = 0; $j < $scheduleQuantity; $j++) {
                    $voucher = $unsoldVouchers[$voucherIndex];
                    $this->line(sprintf('- Voucher ID %d from Offer ID %d', 
                        $voucher->id, 
                        $voucher->merchant_offer_id
                    ));
                    $voucherIndex++;
                }
            }
        }
    }

    private function createMerchantOffer($campaign, $schedule, $quantity, $publishAt = null)
    {
        $publishAt = $publishAt ?? now()->addDay()->startOfDay();
        $now = now();

        // Set status based on available_at date
        $status = $schedule->available_at <= $now 
            ? MerchantOffer::STATUS_PUBLISHED 
            : MerchantOffer::STATUS_DRAFT;

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
            'status' => $status,
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

        if ($this->isDryRun) {
            $this->line(sprintf('  Status: %s', $status == MerchantOffer::STATUS_PUBLISHED ? 'Published' : 'Draft'));
        } else {
            Log::info("[RedistributeCampaignQuantities] Created new offer with status: " . ($status == MerchantOffer::STATUS_PUBLISHED ? 'Published' : 'Draft'), [
                'offer_id' => $newOffer->id,
                'campaign_id' => $campaign->id,
                'available_at' => $schedule->available_at,
                'status' => $status
            ]);
        }

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
