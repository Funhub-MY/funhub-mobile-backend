<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\MerchantOfferCampaign;
use App\Models\MerchantOfferCampaignSchedule;
use App\Models\MerchantOffer;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class FixMerchantOfferSchedules extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'merchant:fix-schedules 
                           {--campaign_sku= : Process a specific campaign by SKU}
                           {--created_at= : Process offers created around this date/time (format: Y-m-d H:i)}
                           {--dry-run : Show what would be updated without making actual changes}
                           {--reset-pattern : Reset the pattern to 3-day intervals starting from the last valid schedule}
                           {--resolve-conflicts : Identify and resolve conflicting schedules}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Fix conflicting merchant offer schedules and ensure proper date sequencing';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $campaignSku = $this->option('campaign_sku');
        $createdAt = $this->option('created_at');
        $dryRun = $this->option('dry-run');
        $resetPattern = $this->option('reset-pattern');
        $resolveConflicts = $this->option('resolve-conflicts');
        $verbose = $this->option('verbose');

        if (!$campaignSku) {
            $this->error('Campaign SKU is required. Use --campaign_sku option.');
            return 1;
        }

        // Find the campaign by SKU
        $campaign = MerchantOfferCampaign::where('sku', $campaignSku)->first();
        if (!$campaign) {
            $this->error("Campaign with SKU '{$campaignSku}' not found.");
            return 1;
        }

        $this->info("Found campaign: {$campaign->name} (ID: {$campaign->id}, SKU: {$campaign->sku})");

        // Find all offers for this campaign
        $offersQuery = MerchantOffer::where('merchant_offer_campaign_id', $campaign->id);
        
        // If created_at provided, filter by creation date
        if ($createdAt) {
            try {
                $createdAtDate = Carbon::parse($createdAt);
                $offersQuery->whereBetween('created_at', [
                    $createdAtDate->copy()->subMinutes(60),
                    $createdAtDate->copy()->addMinutes(60)
                ]);
                $this->info("Filtering offers created around {$createdAt}");
            } catch (\Exception $e) {
                $this->error("Invalid created_at format. Use Y-m-d H:i format (e.g., 2025-03-11 12:40)");
                return 1;
            }
        }

        $offers = $offersQuery->orderBy('created_at')->get();
        
        if ($offers->isEmpty()) {
            $this->warn("No offers found matching the criteria.");
            return 0;
        }

        $this->info("Found {$offers->count()} offers to process.");

        // Check for conflicting schedules
        if ($resolveConflicts) {
            $this->identifyAndResolveConflicts($campaign, $offers, $dryRun);
            return 0;
        }

        // Get all schedules for the campaign
        $schedules = $campaign->schedules()->orderBy('available_at')->get();
        
        // Find conflicting schedules with the same date ranges
        $dateRangeMap = [];
        $conflictingSchedules = [];
        
        foreach ($schedules as $schedule) {
            $dateKey = $schedule->available_at . '_' . $schedule->available_until;
            if (!isset($dateRangeMap[$dateKey])) {
                $dateRangeMap[$dateKey] = [];
            }
            $dateRangeMap[$dateKey][] = $schedule->id;
            
            if (count($dateRangeMap[$dateKey]) > 1) {
                $conflictingSchedules[$dateKey] = $dateRangeMap[$dateKey];
            }
        }
        
        if (!empty($conflictingSchedules) && $verbose) {
            $this->warn("Found conflicting schedules with identical date ranges:");
            foreach ($conflictingSchedules as $dateRange => $scheduleIds) {
                $dates = explode('_', $dateRange);
                $this->line("  Date range {$dates[0]} to {$dates[1]} has " . count($scheduleIds) . " schedules: " . implode(', ', $scheduleIds));
            }
        }

        // Find the last valid schedule (the one with the latest end date) before the created offers
        $lastValidSchedule = null;
        
        if ($resetPattern) {
            // Find the last valid schedule before the targeted offers
            $minCreatedAt = $offers->min('created_at');
            
            // Get the latest schedule that was created before our target offers
            $lastValidSchedule = $campaign->schedules()
                ->where('created_at', '<', $minCreatedAt)
                ->orderBy('available_until', 'desc')
                ->first();
            
            if (!$lastValidSchedule) {
                $this->warn("No previous valid schedule found. Will use the earliest offer's available_until as a starting point.");
                
                // Use the earliest offer in the system as a starting point
                $earliestOffer = MerchantOffer::where('merchant_offer_campaign_id', $campaign->id)
                    ->orderBy('available_at', 'asc')
                    ->first();
                
                if ($earliestOffer) {
                    $lastAvailableUntil = Carbon::parse($earliestOffer->available_until);
                } else {
                    $lastAvailableUntil = Carbon::now()->startOfDay();
                }
            } else {
                $lastAvailableUntil = Carbon::parse($lastValidSchedule->available_until);
                $this->info("Last valid schedule ends at: {$lastAvailableUntil}");
            }
        } else {
            // Find the last scheduled offer date
            $lastScheduledOffer = MerchantOffer::where('merchant_offer_campaign_id', $campaign->id)
                ->whereNotIn('id', $offers->pluck('id')->toArray())
                ->orderBy('available_until', 'desc')
                ->first();
            
            if ($lastScheduledOffer) {
                $lastAvailableUntil = Carbon::parse($lastScheduledOffer->available_until);
                $this->info("Last scheduled offer ends at: {$lastAvailableUntil}");
            } else {
                $this->warn("No previous offers found. Will use current date as starting point.");
                $lastAvailableUntil = Carbon::now()->startOfDay();
            }
        }

        $updateData = [];
        $updatedSchedules = [];
        
        // Generate new date ranges for each offer
        foreach ($offers as $index => $offer) {
            $startDate = $lastAvailableUntil->copy()->addSecond()->startOfDay();
            $endDate = $startDate->copy()->addDays(3)->subSecond();
            
            $updatedRecord = [
                'offer_id' => $offer->id,
                'schedule_id' => $offer->schedule_id,
                'old_available_at' => $offer->available_at,
                'old_available_until' => $offer->available_until,
                'new_available_at' => $startDate->format('Y-m-d H:i:s'),
                'new_available_until' => $endDate->format('Y-m-d H:i:s'),
                'new_publish_at' => $startDate->format('Y-m-d H:i:s'),
            ];
            
            $updateData[] = $updatedRecord;
            
            // Add to schedules update data if not yet included
            if (!isset($updatedSchedules[$offer->schedule_id])) {
                $updatedSchedules[$offer->schedule_id] = $updatedRecord;
            }
            
            // Update for next iteration
            $lastAvailableUntil = $endDate;
        }

        // Display the update plan
        $this->info("Update plan for offers:");
        $this->table(
            ['Offer ID', 'Schedule ID', 'Old Start', 'Old End', 'New Start', 'New End'],
            array_map(function($item) {
                return [
                    $item['offer_id'],
                    $item['schedule_id'],
                    $item['old_available_at'],
                    $item['old_available_until'],
                    $item['new_available_at'],
                    $item['new_available_until']
                ];
            }, $updateData)
        );
        
        $this->info("Update plan for schedules:");
        $this->table(
            ['Schedule ID', 'New Start', 'New End'],
            array_map(function($item) {
                return [
                    $item['schedule_id'],
                    $item['new_available_at'],
                    $item['new_available_until']
                ];
            }, $updatedSchedules)
        );

        if ($dryRun) {
            $this->warn("This was a dry run. No changes were made. Remove --dry-run to apply these changes.");
            return 0;
        }

        // Confirm before proceeding
        if (!$this->confirm('Do you want to apply these changes?', true)) {
            $this->info('Operation cancelled.');
            return 0;
        }

        // Apply the updates
        DB::beginTransaction();
        try {
            $now = Carbon::now();
            
            // Update offers
            foreach ($updateData as $update) {
                $startDate = Carbon::parse($update['new_available_at']);
                $endDate = Carbon::parse($update['new_available_until']);
                
                // Determine status based on date range
                // If the date range is current (now is between start and end), set status to 1 (Published)
                // If the date range is in the future or past, set status to 0 (Draft)
                $status = ($now->between($startDate, $endDate)) ? 1 : 0;
                
                MerchantOffer::where('id', $update['offer_id'])
                    ->update([
                        'available_at' => $update['new_available_at'],
                        'available_until' => $update['new_available_until'],
                        'publish_at' => $update['new_publish_at'],
                        'status' => $status
                    ]);
                
                if ($this->option('verbose')) {
                    $statusLabel = $status ? 'Published' : 'Draft';
                    $this->line("  Offer #{$update['offer_id']} status set to {$statusLabel} ({$status})");
                }
            }
            
            // Update schedules
            foreach ($updatedSchedules as $scheduleId => $update) {
                $startDate = Carbon::parse($update['new_available_at']);
                $endDate = Carbon::parse($update['new_available_until']);
                
                // Determine status based on date range
                $status = ($now->between($startDate, $endDate)) ? 1 : 0;
                
                MerchantOfferCampaignSchedule::where('id', $scheduleId)
                    ->update([
                        'available_at' => $update['new_available_at'],
                        'available_until' => $update['new_available_until'],
                        'publish_at' => $update['new_publish_at'],
                        'status' => $status
                    ]);
                
                if ($this->option('verbose')) {
                    $statusLabel = $status ? 'Published' : 'Draft';
                    $this->line("  Schedule #{$scheduleId} status set to {$statusLabel} ({$status})");
                }
            }
            
            DB::commit();
            $this->info("Successfully updated {$offers->count()} offers and " . count($updatedSchedules) . " schedules.");
        } catch (\Exception $e) {
            DB::rollBack();
            $this->error("Error updating schedules: " . $e->getMessage());
            Log::error("Error updating merchant offer schedules: " . $e->getMessage());
            Log::error($e->getTraceAsString());
            return 1;
        }

        return 0;
    }

    /**
     * Identify and resolve conflicting schedules
     *
     * @param MerchantOfferCampaign $campaign
     * @param \Illuminate\Support\Collection $offers
     * @param bool $dryRun
     * @return void
     */
    private function identifyAndResolveConflicts($campaign, $offers, $dryRun)
    {
        $this->info("Analyzing schedule conflicts...");

        $dateRanges = [];
        $conflictingOffers = [];
        
        // Group offers by date range to find conflicts
        foreach ($offers as $offer) {
            $dateKey = $offer->available_at . '_' . $offer->available_until;
            
            if (!isset($dateRanges[$dateKey])) {
                $dateRanges[$dateKey] = [
                    'schedules' => [],
                    'offers' => []
                ];
            }
            
            // Add this offer's schedule if not already in the list
            if (!in_array($offer->schedule_id, $dateRanges[$dateKey]['schedules'])) {
                $dateRanges[$dateKey]['schedules'][] = $offer->schedule_id;
            }
            
            $dateRanges[$dateKey]['offers'][] = $offer->id;
            
            // If we have multiple schedules for the same date range, we have a conflict
            if (count($dateRanges[$dateKey]['schedules']) > 1) {
                $conflictingOffers[$dateKey] = $dateRanges[$dateKey];
            }
        }
        
        if (empty($conflictingOffers)) {
            $this->info("No conflicting schedules found among the selected offers.");
            return;
        }
        
        $this->warn("Found " . count($conflictingOffers) . " date ranges with conflicting schedules:");
        
        foreach ($conflictingOffers as $dateRange => $data) {
            $dates = explode('_', $dateRange);
            $this->line("  Date range {$dates[0]} to {$dates[1]}:");
            $this->line("    Schedules: " . implode(', ', $data['schedules']));
            $this->line("    Offers: " . implode(', ', $data['offers']));
        }
        
        if ($dryRun) {
            $this->warn("Dry run mode - no changes will be made.");
            return;
        }
        
        if (!$this->confirm('Do you want to resolve these conflicts by consolidating to a single schedule per date range?', true)) {
            $this->info('Operation cancelled.');
            return;
        }
        
        // Resolve conflicts by keeping only one schedule per date range
        DB::beginTransaction();
        try {
            foreach ($conflictingOffers as $dateRange => $data) {
                $dates = explode('_', $dateRange);
                $primaryScheduleId = $data['schedules'][0]; // Keep the first schedule
                $offersToUpdate = [];
                
                // Find all offers using the secondary schedules
                foreach ($data['offers'] as $offerId) {
                    $offer = $offers->firstWhere('id', $offerId);
                    if ($offer && $offer->schedule_id != $primaryScheduleId) {
                        $offersToUpdate[] = $offer->id;
                    }
                }
                
                if (!empty($offersToUpdate)) {
                    // Get dates from the primary schedule
                    $primarySchedule = MerchantOfferCampaignSchedule::find($primaryScheduleId);
                    $startDate = Carbon::parse($primarySchedule->available_at);
                    $endDate = Carbon::parse($primarySchedule->available_until);
                    
                    // Determine status based on date range
                    $now = Carbon::now();
                    $status = ($now->between($startDate, $endDate)) ? 1 : 0;
                    
                    // Update offers to use the primary schedule and correct status
                    MerchantOffer::whereIn('id', $offersToUpdate)
                        ->update([
                            'schedule_id' => $primaryScheduleId,
                            'available_at' => $primarySchedule->available_at,
                            'available_until' => $primarySchedule->available_until,
                            'publish_at' => $primarySchedule->publish_at,
                            'status' => $status
                        ]);
                    
                    $this->info("Updated " . count($offersToUpdate) . " offers to use schedule #{$primaryScheduleId} for date range {$dates[0]} to {$dates[1]}");
                    
                    // Get secondary schedules that might now be orphaned
                    $secondarySchedules = array_slice($data['schedules'], 1);
                    
                    // Check if any schedules are now orphaned (no offers using them)
                    foreach ($secondarySchedules as $scheduleId) {
                        $offerCount = MerchantOffer::where('schedule_id', $scheduleId)->count();
                        
                        if ($offerCount == 0) {
                            // Delete the orphaned schedule
                            MerchantOfferCampaignSchedule::where('id', $scheduleId)->delete();
                            $this->info("Deleted orphaned schedule #{$scheduleId}");
                        } else {
                            $this->warn("Schedule #{$scheduleId} still has {$offerCount} offers associated with it");
                        }
                    }
                }
            }
            
            DB::commit();
            $this->info("Successfully resolved schedule conflicts.");
        } catch (\Exception $e) {
            DB::rollBack();
            $this->error("Error resolving conflicts: " . $e->getMessage());
            Log::error("Error resolving schedule conflicts: " . $e->getMessage());
            Log::error($e->getTraceAsString());
        }
    }
}