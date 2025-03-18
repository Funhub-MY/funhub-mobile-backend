<?php

namespace App\Console\Commands;

use App\Jobs\IndexMerchantOffer;
use App\Models\Merchant;
use App\Models\MerchantOffer;
use App\Models\MerchantOfferCampaignSchedule;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AutoArchieveMerchantOffer extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'merchant-offers:auto-archieve';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Commands to auto archieve merchant offers that are sold out (midnight), or past available_until';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        Log::info('[AutoArchiveMerchantOffer] Running AutoArchiveMerchantOffer');
        
        // get sold out offers IDs (all vouchers claimed)
        $soldOutOffersQuery = MerchantOffer::published()
            ->doesntHave('unclaimedVouchers') // all bought
            ->available()
            ->select('id', 'schedule_id');
            
        $soldOutCount = $soldOutOffersQuery->count();
        $this->info("[AutoArchiveMerchantOffer] Found {$soldOutCount} sold out offers to archive");
        Log::info("[AutoArchiveMerchantOffer] Found {$soldOutCount} sold out offers to archive");
        
        // get past available_until offers IDs
        $pastAvailableUntilOffersQuery = MerchantOffer::published()
            ->where('available_until', '<', now())
            ->select('id', 'schedule_id');
            
        $pastAvailableCount = $pastAvailableUntilOffersQuery->count();
        $this->info("[AutoArchiveMerchantOffer] Found {$pastAvailableCount} past available_until offers to archive");
        Log::info("[AutoArchiveMerchantOffer] Found {$pastAvailableCount} past available_until offers to archive");
        
        // combine both queries to get all offers to archive
        $soldOutOfferIds = $soldOutOffersQuery->pluck('id')->toArray();
        $pastAvailableOfferIds = $pastAvailableUntilOffersQuery->pluck('id')->toArray();
        
        $allOfferIdsToArchive = array_unique(array_merge($soldOutOfferIds, $pastAvailableOfferIds));
        
        if (empty($allOfferIdsToArchive)) {
            $this->info('No offers to archive');
            Log::info('[AutoArchiveMerchantOffer] No offers to archive');
            return Command::SUCCESS;
        }
        
        // batch update all offers in a single query
        $updatedCount = MerchantOffer::whereIn('id', $allOfferIdsToArchive)
            ->update(['status' => MerchantOffer::STATUS_ARCHIVED]);
            
        $this->info("[AutoArchiveMerchantOffer] Archived {$updatedCount} offers in total");
        Log::info("[AutoArchiveMerchantOffer] Archived {$updatedCount} offers in total");
        
        // get all schedule IDs that need to be updated
        $scheduleIds = DB::table('merchant_offers')
            ->whereIn('id', $allOfferIdsToArchive)
            ->whereNotNull('schedule_id')
            ->pluck('schedule_id')
            ->filter()
            ->toArray();
            
        if (!empty($scheduleIds)) {
            // Update all schedules in a single query
            $updatedSchedules = MerchantOfferCampaignSchedule::whereIn('id', $scheduleIds)
                ->update(['status' => MerchantOfferCampaignSchedule::STATUS_ARCHIVED]);
                
            $this->info("[AutoArchiveMerchantOffer] Updated {$updatedSchedules} campaign schedules to archived status");
            Log::info("[AutoArchiveMerchantOffer] Updated {$updatedSchedules} campaign schedules to archived status");
        }
        
        // dispatch search indexing jobs for all archived offers
        if (count($allOfferIdsToArchive) > 0) {
            try {
                $this->info('Dispatching search indexing jobs for archived offers...');
                
                // dispatch jobs in batches to avoid overwhelming the queue
                foreach (array_chunk($allOfferIdsToArchive, 50) as $idsBatch) {
                    foreach ($idsBatch as $offerId) {
                        IndexMerchantOffer::dispatch($offerId);
                    }
                }
                
                $this->info('Dispatched ' . count($allOfferIdsToArchive) . ' indexing jobs to the queue');
                Log::info('[AutoArchiveMerchantOffer] Dispatched ' . count($allOfferIdsToArchive) . ' indexing jobs');
            } catch (\Exception $e) {
                Log::error('[AutoArchiveMerchantOffer] Error dispatching indexing jobs', ['error' => $e->getMessage()]);
            }
        }

        return Command::SUCCESS;
    }
}
