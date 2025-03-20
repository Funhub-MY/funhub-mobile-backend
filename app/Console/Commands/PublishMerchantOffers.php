<?php

namespace App\Console\Commands;

use App\Events\MerchantOfferPublished;
use App\Models\MerchantOffer;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class PublishMerchantOffers extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'merchant-offers:publish';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Publish merchant offers that are in draft status and publish at is less than or equal to current time';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $this->info("[PublishMerchantOffers] Running PublishMerchantOffers");
        Log::info("[PublishMerchantOffers] Running PublishMerchantOffers");

        // get offers that need to be published
        $offersQuery = MerchantOffer::whereNotNull('publish_at')
            ->where('status', MerchantOffer::STATUS_DRAFT)
            ->where('publish_at', '<=', Carbon::now());
            
        $totalCount = $offersQuery->count();
        $this->info("Found {$totalCount} merchant offers to publish");
        Log::info("[PublishMerchantOffers] Found {$totalCount} merchant offers to publish");
        
        if ($totalCount === 0) {
            $this->info("No merchant offers to publish");
            Log::info("[PublishMerchantOffers] No merchant offers to publish");
            return Command::SUCCESS;
        }
        
        // get IDs of offers to be published (before updating them)
        $offerIds = $offersQuery->pluck('id')->toArray();
        
        // single SQL update for all offers
        $updatedCount = MerchantOffer::whereIn('id', $offerIds)
            ->update(['status' => MerchantOffer::STATUS_PUBLISHED]);
            
        $this->info("Updated {$updatedCount} merchant offers to published status");
        Log::info("[PublishMerchantOffers] Updated {$updatedCount} merchant offers to published status");
        
        $processedCount = 0;
        
        // first, update all campaign schedules in a single query
        try {
            // get offers with schedule_id that need their schedules updated
            $offersWithSchedules = MerchantOffer::whereIn('id', $offerIds)
                ->whereNotNull('schedule_id')
                ->whereNotNull('merchant_offer_campaign_id')
                ->select('id', 'schedule_id', 'merchant_offer_campaign_id')
                ->get();
                
            if ($offersWithSchedules->count() > 0) {
                $scheduleIds = $offersWithSchedules->pluck('schedule_id')->filter()->toArray();
                
                if (!empty($scheduleIds)) {
                    // update all schedules in a single query
                    $updatedSchedules = \App\Models\MerchantOfferCampaignSchedule::whereIn('id', $scheduleIds)
                        ->update(['status' => MerchantOffer::STATUS_PUBLISHED]);
                        
                    $this->info("Updated {$updatedSchedules} campaign schedules to published status");
                    Log::info("[PublishMerchantOffers] Updated {$updatedSchedules} campaign schedules");
                }
            }
        } catch (\Exception $e) {
            Log::error('[PublishMerchantOffers] Error batch updating schedules', ['error' => $e->getMessage()]);
        }
            
        $this->info("Successfully processed {$processedCount} merchant offers");
        Log::info("[PublishMerchantOffers] Successfully processed {$processedCount} merchant offers");
        
        if (count($offerIds) > 0) {
            try {
                $this->info('Dispatching search indexing jobs for newly published offers...');
                
                // dispatch jobs for each offer ID to be processed in the queue
                foreach ($offerIds as $offerId) {
                    // important as single update query will not trigger scout to index
                    \App\Jobs\IndexMerchantOffer::dispatch($offerId);
                }
                
                $this->info('Dispatched ' . count($offerIds) . ' indexing jobs to the queue');
            } catch (\Exception $e) {
                Log::error('[PublishMerchantOffers] Error dispatching indexing jobs', ['error' => $e->getMessage()]);
            }
        } else {
            $this->info('No offers were published, skipping search index sync');
        }
        
        $this->info("Firing events for published offers...");
        foreach (array_chunk($offerIds, 50) as $idsBatch) {
            // get the offers that were just updated
            $offers = MerchantOffer::whereIn('id', $idsBatch)->get();
            
            foreach ($offers as $offer) {
                try {
                    event(new MerchantOfferPublished($offer));
                    Log::info('[PublishMerchantOffers] Merchant offer processed', ['offer_id' => $offer->id]);
                    $processedCount++;
                } catch (\Exception $e) {
                    Log::error('[PublishMerchantOffers] Error firing event', [
                        'offer_id' => $offer->id,
                        'error' => $e->getMessage()
                    ]);
                }
            }
            
            // Free up memory after each batch
            unset($offers);
        }

        return Command::SUCCESS;
    }
}
