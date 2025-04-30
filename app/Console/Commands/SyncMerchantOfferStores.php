<?php

namespace App\Console\Commands;

use App\Models\MerchantOffer;
use App\Models\MerchantOfferCampaign;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SyncMerchantOfferStores extends Command
{
    /**
     * The name and signature of the console command.
     * Example: php artisan merchant:sync-offer-stores {campaign_id} --dry-run
     * Example: php artisan merchant:sync-offer-stores --dry-run  (Checks all active/draft)
     *
     * @var string
     */
    protected $signature = 'merchant:sync-offer-stores {campaign? : The ID of a specific campaign to sync} {--dry-run : If specified, no changes will be made to the database}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Synchronizes MerchantOffer stores based on their parent Campaign stores. Checks a specific campaign or all active/draft ones.';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $isDryRun = $this->option('dry-run');
        $campaignId = $this->argument('campaign');

        // Build the base query
        $campaignQuery = MerchantOfferCampaign::query();

        if ($campaignId) {
            $this->info("Processing specific campaign ID: {$campaignId}");
            $campaignQuery->where('id', $campaignId);
        } else {
            $this->info("Processing all active and draft campaigns (Status 0 or 1)...");
            $campaignQuery->whereIn('status', [
                MerchantOfferCampaign::STATUS_DRAFT,
                MerchantOfferCampaign::STATUS_PUBLISHED
            ]);
        }

        $totalCampaigns = $campaignQuery->count();

        if ($totalCampaigns === 0) {
            $this->info('No campaigns found matching the criteria.');
            return Command::SUCCESS;
        }

        $progressBar = $this->output->createProgressBar($totalCampaigns);

        $this->info($isDryRun ? 'Starting sync check (Dry Run)...' : 'Starting synchronization...');
        $progressBar->start();

        $processedCampaigns = 0;
        $issuesFound = 0; // Counts offers needing sync
        $totalStoresAdded = 0;
        $totalStoresRemoved = 0;

        // Use the built query for chunking
        // Eager load stores relation for offers ONLY if doing a dry run
        $relationsToLoad = $isDryRun ? ['merchantOffers.stores'] : ['merchantOffers'];

        $campaignQuery->with($relationsToLoad)->chunk(100, function ($campaigns) use ($progressBar, $isDryRun, &$processedCampaigns, &$issuesFound, &$totalStoresAdded, &$totalStoresRemoved) {
            foreach ($campaigns as $campaign) {
                // Get campaign stores as a sorted collection of IDs
                $campaignStoreIds = DB::table('merchant_offer_campaign_stores')
                    ->where('merchant_offer_campaign_id', $campaign->id)
                    ->pluck('store_id')
                    ->unique()
                    ->sort()
                    ->values();

                foreach ($campaign->merchantOffers as $offer) {
                    if ($isDryRun) {
                        // Dry Run: Calculate and report differences
                        $offerStoreIds = $offer->stores->pluck('id')->unique()->sort()->values();

                        $storesToAdd = $campaignStoreIds->diff($offerStoreIds);
                        $storesToRemove = $offerStoreIds->diff($campaignStoreIds);

                        if ($storesToAdd->isNotEmpty() || $storesToRemove->isNotEmpty()) {
                            $issuesFound++;
                            $this->warn("\n[Dry Run] Discrepancy for Campaign ID: {$campaign->id}, Offer ID: {$offer->id}");
                            if ($storesToAdd->isNotEmpty()) {
                                $this->line('  Stores to ADD: ' . $storesToAdd->implode(', '));
                            }
                            if ($storesToRemove->isNotEmpty()) {
                                $this->line('  Stores to REMOVE: ' . $storesToRemove->implode(', '));
                            }
                        }
                    } else {
                        // Actual Run: Use sync()
                        $syncResult = $offer->stores()->sync($campaignStoreIds->all()); // Pass array to sync
                        $this->info(string: "\nSynced Offer ID: {$offer->id} synced ID:" . $campaignStoreIds->implode(', '));

                        $storesAdded = count($syncResult['attached']);
                        $storesRemoved = count($syncResult['detached']);

                        if ($storesAdded > 0 || $storesRemoved > 0) {
                            $issuesFound++; // Count this offer as having changes
                            $totalStoresAdded += $storesAdded;
                            $totalStoresRemoved += $storesRemoved;
                             // Optional: Log the specific offer sync
                             // $this->line("\nSynced Offer ID: {$offer->id} (Added: {$storesAdded}, Removed: {$storesRemoved})");
                        }
                    }
                } // end foreach offer

                $processedCampaigns++;
                $progressBar->advance();
            } // end foreach campaign
        }); // end chunk

        $progressBar->finish();
        $this->info("\n----------------------------------------");
        $this->info("Processed {$processedCampaigns} campaigns.");
        if ($issuesFound > 0) {
             $this->warn("Found {$issuesFound} offer(s) needing synchronization.");
            if ($isDryRun) {
                $this->info("Dry run complete. No changes were made.");
            } else {
                $this->info("Synchronization complete.");
                $this->info("Total Stores Attached: {$totalStoresAdded}");
                $this->info("Total Stores Detached: {$totalStoresRemoved}");
            }
        } else {
            $this->info("All relevant merchant offer stores are in sync with their campaigns.");
        }

        return Command::SUCCESS;
    }
}
