<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\MerchantOfferCampaign;
use App\Models\MerchantOfferCampaignSchedule;
use App\Models\MerchantOffer;
use App\Models\MerchantOfferVoucher;
use App\Models\MerchantOfferVoucherMovement;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class RebalanceMerchantOfferVouchers extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'merchant:rebalance-vouchers 
                            {--campaign_id= : Process a specific campaign by ID}
                            {--campaign_sku= : Process a specific campaign by SKU}
                            {--fix : Actually apply the changes}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Identify and rebalance merchant offer vouchers to match agreement quantities';

    /**
     * Constants for offer creation
     */
    private const DEFAULT_QUANTITY_PER_OFFER = 5;
    private const DEFAULT_SCHEDULE_DAYS = 3;

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $campaignId = $this->option('campaign_id');
        $campaignSku = $this->option('campaign_sku');
        $fix = $this->option('fix');
        $verbose = $this->option('verbose');
        
        $this->info('Starting voucher rebalancing process...');
        
        // Step 1: Identify campaigns with voucher discrepancies
        $this->info('Step 1: Identifying campaigns with voucher count mismatches...');
        
        $query = MerchantOfferCampaign::query();
        
        if ($campaignId) {
            $query->where('id', $campaignId);
        } else if ($campaignSku) {
            $query->where('sku', $campaignSku);
        }
        
        $campaigns = $query->get();
        $this->info('Found ' . $campaigns->count() . ' campaigns to analyze.');
        
        $discrepancyData = [];
        
        foreach ($campaigns as $campaign) {
            // Get agreement quantity
            $agreementQuantity = $campaign->agreement_quantity;
            
            // Count vouchers through merchant offers
            $voucherCount = MerchantOfferVoucher::whereHas('merchant_offer', function ($query) use ($campaign) {
                $query->where('merchant_offer_campaign_id', $campaign->id);
            })->count();
            
            // Calculate discrepancy
            $discrepancy = $agreementQuantity - $voucherCount;
            
            // Count owned vouchers
            $ownedVoucherCount = MerchantOfferVoucher::whereHas('merchant_offer', function ($query) use ($campaign) {
                $query->where('merchant_offer_campaign_id', $campaign->id);
            })->whereNotNull('owned_by_id')->count();
            
            // Only process campaigns with discrepancy
            if ($discrepancy != 0) {
                $discrepancyData[] = [
                    'campaign_id' => $campaign->id,
                    'campaign_sku' => $campaign->sku,
                    'campaign_name' => $campaign->name,
                    'agreement_quantity' => $agreementQuantity,
                    'voucher_count' => $voucherCount,
                    'owned_voucher_count' => $ownedVoucherCount,
                    'discrepancy' => $discrepancy
                ];
                
                if ($verbose) {
                    $this->info("Campaign ID {$campaign->id} ({$campaign->sku}): Agreement: {$agreementQuantity}, Vouchers: {$voucherCount}, Owned: {$ownedVoucherCount}, Discrepancy: {$discrepancy}");
                }
            }
        }
        
        if (empty($discrepancyData)) {
            $this->info('No campaigns with voucher discrepancies found.');
            return 0;
        }
        
        $this->table(
            ['Campaign ID', 'SKU', 'Name', 'Agreement Qty', 'Voucher Count', 'Owned Vouchers', 'Discrepancy'],
            $discrepancyData
        );
        
        // Step 2: Process each campaign
        $this->info('Step 2: Processing campaigns with discrepancies...');
        
        $campaignsToFix = collect($discrepancyData);
        
        foreach ($campaignsToFix as $discrepancy) {
            $campaignId = $discrepancy['campaign_id'];
            $campaign = MerchantOfferCampaign::find($campaignId);
            $diff = $discrepancy['discrepancy'];
            
            $this->info("Processing campaign {$campaign->id} ({$campaign->sku}) - Discrepancy: {$diff}");
            
            // Check for duplicate schedules first (major potential problem)
            $hasDuplicates = $this->checkAndFixDuplicateSchedules($campaign, $fix, $verbose);
            
            if ($diff < 0) {
                // Too many vouchers - remove extras
                $this->removeExtraVouchers($campaign, abs($diff), $fix, $verbose);
            } else {
                // Too few vouchers - add more
                $this->addMissingVouchers($campaign, $diff, $fix, $verbose);
            }
            
            // Finally, update the agreement quantity if needed
            if ($fix && !$hasDuplicates) {
                $updatedVoucherCount = MerchantOfferVoucher::whereHas('merchant_offer', function ($query) use ($campaign) {
                    $query->where('merchant_offer_campaign_id', $campaign->id);
                })->count();
                
                // $campaign->agreement_quantity = $updatedVoucherCount;
                // $campaign->save();
                
                $this->info("Updated campaign {$campaign->id} , total vouchers is now: {$updatedVoucherCount}");
            }
        }
        
        if (!$fix) {
            $this->warn('Use the --fix option to apply these changes. This was a dry run only.');
        }
        
        $this->info('Rebalancing process completed.');
        return 0;
    }
    
    /**
     * Check for and fix duplicate schedules
     */
    private function checkAndFixDuplicateSchedules(MerchantOfferCampaign $campaign, bool $fix, bool $verbose)
    {
        $this->info("Checking for duplicate schedules in campaign {$campaign->id}...");
        
        // Group schedules by date ranges to find duplicates
        $schedules = $campaign->schedules()->orderBy('available_at')->get();
        
        $dateGroups = [];
        foreach ($schedules as $schedule) {
            $dateKey = $schedule->available_at . '_' . $schedule->available_until;
            if (!isset($dateGroups[$dateKey])) {
                $dateGroups[$dateKey] = [];
            }
            $dateGroups[$dateKey][] = $schedule;
        }
        
        $duplicateSchedules = [];
        foreach ($dateGroups as $dateKey => $scheduleGroup) {
            if (count($scheduleGroup) > 1) {
                // Keep the first schedule, mark the rest as duplicates
                $primarySchedule = array_shift($scheduleGroup);
                foreach ($scheduleGroup as $duplicateSchedule) {
                    $duplicateSchedules[] = [
                        'schedule_id' => $duplicateSchedule->id,
                        'primary_schedule_id' => $primarySchedule->id,
                        'available_at' => $duplicateSchedule->available_at,
                        'available_until' => $duplicateSchedule->available_until,
                        'quantity' => $duplicateSchedule->quantity
                    ];
                }
            }
        }
        
        if (empty($duplicateSchedules)) {
            $this->info("No duplicate schedules found for campaign {$campaign->id}");
            return false;
        }
        
        $this->info("Found " . count($duplicateSchedules) . " duplicate schedules for campaign {$campaign->id}");
        
        if ($verbose) {
            $this->table(
                ['Schedule ID', 'Primary Schedule ID', 'Available From', 'Available Until', 'Quantity'],
                $duplicateSchedules
            );
        }
        
        if ($fix) {
            $this->info("Fixing duplicate schedules for campaign {$campaign->id}...");
            
            DB::beginTransaction();
            try {
                foreach ($duplicateSchedules as $duplicateData) {
                    $duplicateScheduleId = $duplicateData['schedule_id'];
                    $primaryScheduleId = $duplicateData['primary_schedule_id'];
                    
                    // Find all offers associated with the duplicate schedule
                    $duplicateOffers = MerchantOffer::where('schedule_id', $duplicateScheduleId)
                        ->where('merchant_offer_campaign_id', $campaign->id)
                        ->get();
                    
                    $this->info("Processing {$duplicateOffers->count()} offers for duplicate schedule {$duplicateScheduleId}");
                    
                    foreach ($duplicateOffers as $duplicateOffer) {
                        // Check for unowned vouchers
                        $unownedVouchers = $duplicateOffer->vouchers()->whereNull('owned_by_id')->get();
                        
                        if ($unownedVouchers->count() > 0) {
                            $this->info("Deleting {$unownedVouchers->count()} unowned vouchers for offer {$duplicateOffer->id}");
                            
                            // Delete unowned vouchers
                            foreach ($unownedVouchers as $voucher) {
                                // Additional check to ensure voucher is not claimed
                                if (!$voucher->claim || !$voucher->redeem) {
                                    $voucher->delete();
                                } else {
                                    $this->warn("Voucher {$voucher->id} has claims or redeems, skipping delete");
                                }
                            }
                        }
                        
                        // Check if there are any owned vouchers left
                        $remainingVouchers = $duplicateOffer->vouchers()->whereNotNull('owned_by_id')->count();
                        
                        if ($remainingVouchers == 0) {
                            // Delete offer media
                            foreach ($duplicateOffer->getMedia(MerchantOffer::MEDIA_COLLECTION_NAME) as $media) {
                                $media->delete();
                            }
                            
                            foreach ($duplicateOffer->getMedia(MerchantOffer::MEDIA_COLLECTION_HORIZONTAL_BANNER) as $media) {
                                $media->delete();
                            }
                            
                            // Delete offer
                            $this->info("Deleting duplicate offer {$duplicateOffer->id}");
                            $duplicateOffer->delete();
                        } else {
                            $this->warn("Offer {$duplicateOffer->id} still has {$remainingVouchers} owned vouchers, cannot delete");
                        }
                    }
                    
                    // Check if all associated offers are deleted
                    $remainingOffersCount = MerchantOffer::where('schedule_id', $duplicateScheduleId)
                        ->where('merchant_offer_campaign_id', $campaign->id)
                        ->count();
                    
                    if ($remainingOffersCount == 0) {
                        // Delete the duplicate schedule
                        $this->info("Deleting duplicate schedule {$duplicateScheduleId}");
                        MerchantOfferCampaignSchedule::where('id', $duplicateScheduleId)->delete();
                    } else {
                        $this->warn("Schedule {$duplicateScheduleId} still has {$remainingOffersCount} offers, cannot delete");
                    }
                }
                
                DB::commit();
                $this->info("Successfully fixed duplicate schedules for campaign {$campaign->id}");
                return true;
            } catch (\Exception $e) {
                DB::rollBack();
                $this->error("Error fixing duplicate schedules for campaign {$campaign->id}: " . $e->getMessage());
                Log::error("Error fixing duplicate schedules for campaign {$campaign->id}: " . $e->getMessage());
                Log::error($e->getTraceAsString());
                return false;
            }
        }
        
        return true;
    }
    
    /**
     * Remove extra vouchers to match agreement quantity
     */
    private function removeExtraVouchers(MerchantOfferCampaign $campaign, int $excessCount, bool $fix, bool $verbose)
    {
        $this->info("Campaign {$campaign->id} has {$excessCount} excess vouchers to remove");
        
        // Get unowned vouchers that can be safely removed
        $unownedVouchers = MerchantOfferVoucher::whereHas('merchant_offer', function ($query) use ($campaign) {
            $query->where('merchant_offer_campaign_id', $campaign->id);
        })->whereNull('owned_by_id')
          ->doesntHave('claim')
          ->doesntHave('redeem')
          ->orderBy('id', 'desc')
          ->limit($excessCount)
          ->get();
        
        if ($unownedVouchers->count() < $excessCount) {
            $this->warn("Only found {$unownedVouchers->count()} unowned vouchers to remove out of {$excessCount} needed");
        }
        
        if ($verbose) {
            $this->info("Found {$unownedVouchers->count()} vouchers to remove");
            foreach ($unownedVouchers as $index => $voucher) {
                $this->line("Voucher ID: {$voucher->id}, Offer ID: {$voucher->merchant_offer_id}");
            }
        }
        
        if ($fix && $unownedVouchers->count() > 0) {
            DB::beginTransaction();
            try {
                // Group vouchers by offer ID for efficient processing
                $vouchersByOffer = $unownedVouchers->groupBy('merchant_offer_id');
                
                foreach ($vouchersByOffer as $offerId => $offerVouchers) {
                    $offer = MerchantOffer::find($offerId);
                    
                    // Delete vouchers
                    foreach ($offerVouchers as $voucher) {
                        $this->line("Deleting voucher {$voucher->id} from offer {$voucher->merchant_offer_id}");
                        $voucher->delete();
                    }
                    
                    // Check if offer is now empty
                    $remainingVouchers = $offer->vouchers()->count();
                    if ($remainingVouchers == 0) {
                        // Delete offer media
                        foreach ($offer->getMedia(MerchantOffer::MEDIA_COLLECTION_NAME) as $media) {
                            $media->delete();
                        }
                        
                        foreach ($offer->getMedia(MerchantOffer::MEDIA_COLLECTION_HORIZONTAL_BANNER) as $media) {
                            $media->delete();
                        }
                        
                        // Delete offer
                        $this->info("Deleting empty offer {$offer->id}");
                        $offer->delete();
                        
                        // Check if schedule is now empty
                        $scheduleId = $offer->schedule_id;
                        $remainingOffers = MerchantOffer::where('schedule_id', $scheduleId)->count();
                        
                        if ($remainingOffers == 0) {
                            $this->info("Deleting empty schedule {$scheduleId}");
                            MerchantOfferCampaignSchedule::where('id', $scheduleId)->delete();
                        }
                    } else {
                        // Update offer quantity
                        $offer->quantity = $remainingVouchers;
                        $offer->save();
                        $this->info("Updated offer {$offer->id} quantity to {$remainingVouchers}");
                    }
                }
                
                DB::commit();
                $this->info("Successfully removed {$unownedVouchers->count()} excess vouchers from campaign {$campaign->id}");
            } catch (\Exception $e) {
                DB::rollBack();
                $this->error("Error removing excess vouchers for campaign {$campaign->id}: " . $e->getMessage());
                Log::error("Error removing excess vouchers for campaign {$campaign->id}: " . $e->getMessage());
                Log::error($e->getTraceAsString());
            }
        }
    }
    
    /**
     * Add missing vouchers to reach the agreement quantity
     */
    private function addMissingVouchers(MerchantOfferCampaign $campaign, int $missingCount, bool $fix, bool $verbose)
    {
        $this->info("Campaign {$campaign->id} needs {$missingCount} additional vouchers");
        
        // Calculate how many offers we need to create
        $offersNeeded = ceil($missingCount / self::DEFAULT_QUANTITY_PER_OFFER);
        
        if ($verbose) {
            $this->info("Need to create {$offersNeeded} new offers with approximately {$missingCount} total vouchers");
        }
        
        if ($fix) {
            DB::beginTransaction();
            try {
                // Find the latest schedule or create a new one
                $latestSchedule = $campaign->schedules()
                    ->where('available_until', '>=', now())
                    ->orderBy('available_until', 'desc')
                    ->first();
                
                $vouchersAdded = 0;
                $startDate = now()->startOfDay();
                
                for ($i = 0; $i < $offersNeeded; $i++) {
                    // If we've added enough vouchers, break out
                    if ($vouchersAdded >= $missingCount) {
                        break;
                    }
                    
                    // Create a new schedule if needed
                    if (!$latestSchedule || $i > 0) {
                        $availableAt = $startDate->copy()->addDays($i * self::DEFAULT_SCHEDULE_DAYS)->startOfDay();
                        $availableUntil = $availableAt->copy()->addDays(self::DEFAULT_SCHEDULE_DAYS)->subSecond();
                        
                        $scheduleQuantity = min(self::DEFAULT_QUANTITY_PER_OFFER, $missingCount - $vouchersAdded);
                        
                        $latestSchedule = $this->createSchedule($campaign, $availableAt, $availableUntil, $scheduleQuantity);
                    }
                    
                    // Calculate how many vouchers to add in this offer
                    $vouchersToAdd = min(self::DEFAULT_QUANTITY_PER_OFFER, $missingCount - $vouchersAdded);
                    
                    // Create a new offer for this schedule
                    $offer = $this->createOffer($campaign, $latestSchedule, $vouchersToAdd);
                    
                    // Create the vouchers
                    for ($j = 0; $j < $vouchersToAdd; $j++) {
                        $voucher = new MerchantOfferVoucher();
                        $voucher->merchant_offer_id = $offer->id;
                        $voucher->code = MerchantOfferVoucher::generateCode();
                        $voucher->voided = 0;
                        $voucher->save();
                        
                        $vouchersAdded++;
                    }
                    
                    $this->info("Created offer {$offer->id} with {$vouchersToAdd} vouchers for schedule {$latestSchedule->id}");
                }
                
                DB::commit();
                $this->info("Successfully added {$vouchersAdded} vouchers to campaign {$campaign->id}");
            } catch (\Exception $e) {
                DB::rollBack();
                $this->error("Error adding vouchers to campaign {$campaign->id}: " . $e->getMessage());
                Log::error("Error adding vouchers to campaign {$campaign->id}: " . $e->getMessage());
                Log::error($e->getTraceAsString());
            }
        }
    }
    
    /**
     * Create a new schedule for the campaign
     */
    private function createSchedule(MerchantOfferCampaign $campaign, $availableAt, $availableUntil, $quantity)
    {
        $schedule = new MerchantOfferCampaignSchedule();
        $schedule->merchant_offer_campaign_id = $campaign->id;
        $schedule->user_id = $campaign->user_id;
        $schedule->status = MerchantOfferCampaignSchedule::STATUS_DRAFT;
        $schedule->publish_at = $availableAt;
        $schedule->available_at = $availableAt;
        $schedule->available_until = $availableUntil;
        $schedule->quantity = $quantity;
        $schedule->flash_deal = $campaign->flash_deal ?? 0;
        $schedule->expiry_days = $campaign->expiry_days;
        $schedule->save();
        
        $this->info("Created new schedule {$schedule->id} from {$availableAt} to {$availableUntil} with quantity {$quantity}");
        
        return $schedule;
    }
    
    /**
     * Create a new offer for the schedule
     */
    private function createOffer($campaign, $schedule, $quantity)
    {
        // Check if offer already exists for this schedule
        $existingOffer = MerchantOffer::where('schedule_id', $schedule->id)->first();
        if ($existingOffer) {
            $this->info("Using existing offer {$existingOffer->id} for schedule {$schedule->id}");
            return $existingOffer;
        }
        
        // Set status based on available_at date
        $status = $schedule->available_at <= now() 
            ? MerchantOffer::STATUS_PUBLISHED 
            : MerchantOffer::STATUS_DRAFT;
        
        $offer = new MerchantOffer();
        $offer->user_id = $campaign->user_id;
        $offer->store_id = $campaign->store_id;
        $offer->merchant_offer_campaign_id = $campaign->id;
        $offer->schedule_id = $schedule->id;
        $offer->name = $campaign->name;
        $offer->quantity = $quantity;
        $offer->description = $campaign->description;
        $offer->sku = $campaign->sku . '-' . $schedule->id;
        $offer->available_at = $schedule->available_at;
        $offer->available_until = $schedule->available_until;
        $offer->publish_at = $schedule->publish_at;
        $offer->flash_deal = $campaign->flash_deal ?? 0;
        $offer->expiry_days = $campaign->expiry_days;
        $offer->status = $status;
        $offer->unit_price = $campaign->unit_price;
        $offer->discounted_point_fiat_price = $campaign->discounted_point_fiat_price;
        $offer->point_fiat_price = $campaign->point_fiat_price;
        $offer->discounted_fiat_price = $campaign->discounted_fiat_price;
        $offer->fiat_price = $campaign->fiat_price;
        $offer->fine_print = $campaign->fine_print;
        $offer->redemption_policy = $campaign->redemption_policy;
        $offer->cancellation_policy = $campaign->cancellation_policy;
        $offer->purchase_method = $campaign->purchase_method;
        $offer->save();
        
        // Copy media and sync relations
        $this->copyMediaAndSyncRelations($campaign, $offer);
        
        return $offer;
    }
    
    /**
     * Copy media and sync relations from campaign to offer
     */
    private function copyMediaAndSyncRelations($campaign, $offer)
    {
        // Copy gallery media
        $mediaItems = $campaign->getMedia(MerchantOfferCampaign::MEDIA_COLLECTION_NAME);
        foreach ($mediaItems as $mediaItem) {
            $mediaItem->copy($offer, MerchantOffer::MEDIA_COLLECTION_NAME);
        }
        
        // Copy horizontal banner media
        $mediaItems = $campaign->getMedia(MerchantOfferCampaign::MEDIA_COLLECTION_HORIZONTAL_BANNER);
        foreach ($mediaItems as $mediaItem) {
            $mediaItem->copy($offer, MerchantOffer::MEDIA_COLLECTION_HORIZONTAL_BANNER);
        }
        
        // Sync categories and stores
        $offer->allOfferCategories()->sync($campaign->allOfferCategories->pluck('id'));
        $offer->stores()->sync($campaign->stores->pluck('id'));
    }
}