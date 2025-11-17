<?php

namespace App\Console\Commands;

use Exception;
use App\Models\MerchantOffer;
use App\Models\MerchantOfferCampaign;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class SyncOfferRelations extends Command
{
    protected $signature = 'offers:sync-relations 
        {--from= : Start date (Y-m-d)}
        {--to= : End date (Y-m-d)}
        {--campaign-id= : Specific campaign ID to process}
        {--offer-id= : Specific offer ID to process}
        {--dry-run : Show what would be synced without making changes}';

    protected $description = 'Sync missing relations from campaigns to offers and reindex Algolia';

    public function handle()
    {
        $query = MerchantOffer::query()
            ->whereNotNull('merchant_offer_campaign_id')
            ->with(['campaign', 'stores', 'allOfferCategories']);

        if ($campaignId = $this->option('campaign-id')) {
            $query->where('merchant_offer_campaign_id', $campaignId);
        }

        if ($offerId = $this->option('offer-id')) {
            $query->where('id', $offerId);
        }

        if ($fromDate = $this->option('from')) {
            try {
                $from = Carbon::createFromFormat('Y-m-d', $fromDate)->startOfDay();
                $query->where('created_at', '>=', $from);
            } catch (Exception $e) {
                $this->error("Invalid from date format. Use YYYY-MM-DD");
                return 1;
            }
        }

        if ($toDate = $this->option('to')) {
            try {
                $to = Carbon::createFromFormat('Y-m-d', $toDate)->endOfDay();
                $query->where('created_at', '<=', $to);
            } catch (Exception $e) {
                $this->error("Invalid to date format. Use YYYY-MM-DD");
                return 1;
            }
        }

        $offers = $query->get();
        $this->info("Found {$offers->count()} offers to process");

        $synced = 0;
        $errors = 0;

        foreach ($offers as $offer) {
            try {
                $campaign = $offer->campaign;
                if (!$campaign) {
                    continue;
                }

                $needsSync = false;

                // Check stores
                if ($offer->stores->count() === 0 && $campaign->stores->count() > 0) {
                    $needsSync = true;
                    if (!$this->option('dry-run')) {
                        $offer->stores()->sync($campaign->stores->pluck('id'));
                    }
                    $this->info("Offer #{$offer->id}: Synced {$campaign->stores->count()} stores");
                }

                // Check categories
                if ($offer->allOfferCategories->count() === 0 && $campaign->allOfferCategories->count() > 0) {
                    $needsSync = true;
                    if (!$this->option('dry-run')) {
                        $offer->allOfferCategories()->sync($campaign->allOfferCategories->pluck('id'));
                    }
                    $this->info("Offer #{$offer->id}: Synced {$campaign->allOfferCategories->count()} categories");
                }

                if ($needsSync && !$this->option('dry-run')) {
                    $offer->refresh();
                    $offer->searchable();
                    $synced++;
                }

            } catch (Exception $e) {
                $this->error("Error processing offer #{$offer->id}: " . $e->getMessage());
                Log::error('Failed to sync offer relations', [
                    'offer_id' => $offer->id,
                    'campaign_id' => $offer->merchant_offer_campaign_id,
                    'error' => $e->getMessage()
                ]);
                $errors++;
            }
        }

        $this->info("\nCompleted relations sync:");
        $this->info("Synced: {$synced}");
        $this->info("Errors: {$errors}");
        $this->info("Total: {$offers->count()}");

        if ($this->option('dry-run')) {
            $this->warn('This was a dry run. No changes were made.');
        }
    }
}
