<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\MerchantOfferCampaign;
use App\Models\MerchantOfferCampaignSchedule;
use App\Models\MerchantOffer;
use App\Models\MerchantOfferVoucher;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class RebalanceMerchantOfferVouchers extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'merchant:rebalance-vouchers {--campaign_id=} {--fix} {--verbose}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Identify and rebalance merchant offer vouchers with duplicate schedules';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $campaignId = $this->option('campaign_id');
        $fix = $this->option('fix');
        $verbose = $this->option('verbose');
        
        $this->info('Starting voucher rebalancing process...');
        
        // Step 1: Identify campaigns with voucher discrepancies
        $this->info('Step 1: Identifying campaigns with voucher count mismatches...');
        
        $query = MerchantOfferCampaign::query();
        
        if ($campaignId) {
            $query->where('id', $campaignId);
        }
        
        $campaigns = $query->get();
        $this->info('Found ' . $campaigns->count() . ' campaigns to analyze.');
        
        $discrepancyData = [];
        
        foreach ($campaigns as $campaign) {
            // get agreement quantity
            $agreementQuantity = $campaign->agreement_quantity;
            
            // count vouchers through merchant offers
            $voucherCount = MerchantOfferVoucher::whereHas('merchant_offer', function ($query) use ($campaign) {
                $query->where('merchant_offer_campaign_id', $campaign->id);
            })->count();
            
            // calculate discrepancy
            $discrepancy = $agreementQuantity - $voucherCount;
            
            // only process campaigns with discrepancy
            if ($discrepancy != 0) {
                $discrepancyData[] = [
                    'campaign_id' => $campaign->id,
                    'campaign_sku' => $campaign->sku,
                    'campaign_name' => $campaign->name,
                    'agreement_quantity' => $agreementQuantity,
                    'voucher_count' => $voucherCount,
                    'discrepancy' => $discrepancy
                ];
                
                if ($verbose) {
                    $this->info("Campaign ID {$campaign->id} ({$campaign->sku}): Agreement: {$agreementQuantity}, Vouchers: {$voucherCount}, Discrepancy: {$discrepancy}");
                }
            }
        }
        
        if (empty($discrepancyData)) {
            $this->info('No campaigns with voucher discrepancies found.');
            return 0;
        }
        
        $this->table(
            ['Campaign ID', 'SKU', 'Name', 'Agreement Qty', 'Voucher Count', 'Discrepancy'],
            $discrepancyData
        );
        
        // Step 2: Identify duplicated schedules
        $this->info('Step 2: Identifying duplicated schedules...');
        
        $campaignsToFix = collect($discrepancyData)->pluck('campaign_id')->toArray();
        
        foreach ($campaignsToFix as $campaignId) {
            $campaign = MerchantOfferCampaign::find($campaignId);
            $this->info("Analyzing schedules for campaign {$campaign->id} ({$campaign->sku})");
            
            // group schedules by date ranges to find duplicates
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
                    // keep the first schedule, mark the rest as duplicates
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
                continue;
            }
            
            $this->info("Found " . count($duplicateSchedules) . " duplicate schedules for campaign {$campaign->id}");
            
            if ($verbose) {
                $this->table(
                    ['Schedule ID', 'Primary Schedule ID', 'Available From', 'Available Until', 'Quantity'],
                    $duplicateSchedules
                );
            }
            
            // Step 3: Fix the issues if --fix flag is set
            if ($fix) {
                $this->info("Step 3: Fixing duplicate schedules for campaign {$campaign->id}...");
                
                DB::beginTransaction();
                try {
                    foreach ($duplicateSchedules as $duplicateData) {
                        $duplicateScheduleId = $duplicateData['schedule_id'];
                        $primaryScheduleId = $duplicateData['primary_schedule_id'];
                        
                        // find all offers associated with the duplicate schedule
                        $duplicateOffers = MerchantOffer::where('schedule_id', $duplicateScheduleId)
                            ->where('merchant_offer_campaign_id', $campaign->id)
                            ->get();
                        
                        $this->info("Processing {$duplicateOffers->count()} offers for duplicate schedule {$duplicateScheduleId}");
                        
                        foreach ($duplicateOffers as $duplicateOffer) {
                            // check for unowned vouchers
                            $unownedVouchers = $duplicateOffer->vouchers()->whereNull('owned_by_id')->get();
                            
                            if ($unownedVouchers->count() > 0) {
                                $this->info("Deleting {$unownedVouchers->count()} unowned vouchers for offer {$duplicateOffer->id}");
                                
                                // delete unowned vouchers
                                foreach ($unownedVouchers as $voucher) {
                                    // additional check to ensure voucher is not claimed
                                    if (!$voucher->claim || !$voucher->redeem) {
                                        $voucher->delete();
                                    } else {
                                        $this->warn("Voucher {$voucher->id} has claims or redeems, skipping delete");
                                    }
                                }
                            }
                            
                            // check if there are any owned vouchers left
                            $remainingVouchers = $duplicateOffer->vouchers()->whereNotNull('owned_by_id')->count();
                            
                            if ($remainingVouchers == 0) {
                                // delete offer media
                                foreach ($duplicateOffer->getMedia(MerchantOffer::MEDIA_COLLECTION_NAME) as $media) {
                                    $media->delete();
                                }
                                
                                foreach ($duplicateOffer->getMedia(MerchantOffer::MEDIA_COLLECTION_HORIZONTAL_BANNER) as $media) {
                                    $media->delete();
                                }
                                
                                // delete offer
                                $this->info("Deleting duplicate offer {$duplicateOffer->id}");
                                $duplicateOffer->delete();
                            } else {
                                $this->warn("Offer {$duplicateOffer->id} still has {$remainingVouchers} owned vouchers, cannot delete");
                            }
                        }
                        
                        // check if all associated offers are deleted
                        $remainingOffersCount = MerchantOffer::where('schedule_id', $duplicateScheduleId)
                            ->where('merchant_offer_campaign_id', $campaign->id)
                            ->count();
                        
                        if ($remainingOffersCount == 0) {
                            // delete the duplicate schedule
                            $this->info("Deleting duplicate schedule {$duplicateScheduleId}");
                            MerchantOfferCampaignSchedule::where('id', $duplicateScheduleId)->delete();
                        } else {
                            $this->warn("Schedule {$duplicateScheduleId} still has {$remainingOffersCount} offers, cannot delete");
                        }
                    }
                    
                    // update agreement quantity to match actual voucher count
                    $updatedVoucherCount = MerchantOfferVoucher::whereHas('merchant_offer', function ($query) use ($campaign) {
                        $query->where('merchant_offer_campaign_id', $campaign->id);
                    })->count();
                    
                    $campaign->agreement_quantity = $updatedVoucherCount;
                    $campaign->save();
                    
                    $this->info("Updated campaign {$campaign->id} agreement quantity to {$updatedVoucherCount}");
                    
                    DB::commit();
                    $this->info("Successfully fixed campaign {$campaign->id}");
                } catch (\Exception $e) {
                    DB::rollBack();
                    $this->error("Error fixing campaign {$campaign->id}: " . $e->getMessage());
                    Log::error("Error fixing campaign {$campaign->id}: " . $e->getMessage());
                    Log::error($e->getTraceAsString());
                }
            }
        }
        
        if (!$fix) {
            $this->warn('Use the --fix option to apply these changes. This was a dry run only.');
        }
        
        $this->info('Rebalancing process completed.');
        return 0;
    }
}