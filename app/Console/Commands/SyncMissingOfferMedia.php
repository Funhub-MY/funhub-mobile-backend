<?php

namespace App\Console\Commands;

use Exception;
use App\Models\MerchantOffer;
use App\Models\MerchantOfferCampaign;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class SyncMissingOfferMedia extends Command
{
    protected $signature = 'offers:sync-missing-media 
        {--campaign-id= : Specific campaign ID to process}
        {--offer-id= : Specific offer ID to process}
        {--from= : Start date (Y-m-d)}
        {--to= : End date (Y-m-d)}
        {--dry-run : Show what would be synced without making changes}';

    protected $description = 'Sync missing media from campaigns to their offers within a date range';

    public function handle()
    {
        $this->info('Starting to sync missing media from campaigns to offers...');
        
        $query = MerchantOffer::query()
            ->whereNotNull('merchant_offer_campaign_id')
            ->with(['campaign']);

        // filter by campaign if specified
        if ($campaignId = $this->option('campaign-id')) {
            $query->where('merchant_offer_campaign_id', $campaignId);
        }

        // filter by offer if specified
        if ($offerId = $this->option('offer-id')) {
            $query->where('id', $offerId);
        }

        // filter by date range
        if ($fromDate = $this->option('from')) {
            try {
                $from = Carbon::createFromFormat('Y-m-d', $fromDate)->startOfDay();
                $query->where('created_at', '>=', $from);
                $this->info("Filtering offers created from: {$from->format('Y-m-d H:i:s')}");
            } catch (Exception $e) {
                $this->error("Invalid from date format. Use YYYY-MM-DD");
                return 1;
            }
        }

        if ($toDate = $this->option('to')) {
            try {
                $to = Carbon::createFromFormat('Y-m-d', $toDate)->endOfDay();
                $query->where('created_at', '<=', $to);
                $this->info("Filtering offers created until: {$to->format('Y-m-d H:i:s')}");
            } catch (Exception $e) {
                $this->error("Invalid to date format. Use YYYY-MM-DD");
                return 1;
            }
        }

        $totalOffers = $query->count();
        $this->info("Found {$totalOffers} offers to process");

        $bar = $this->output->createProgressBar($totalOffers);
        $bar->start();

        $synced = 0;
        $errors = 0;
        $skipped = 0;

        foreach ($query->cursor() as $offer) {
            try {
                $campaign = $offer->campaign;
                if (!$campaign) {
                    $this->warn("Offer #{$offer->id} has no campaign");
                    $skipped++;
                    continue;
                }

                $needsSync = false;

                // check gallery media
                $offerGalleryCount = $offer->getMedia(MerchantOffer::MEDIA_COLLECTION_NAME)->count();
                $campaignGalleryCount = $campaign->getMedia(MerchantOfferCampaign::MEDIA_COLLECTION_NAME)->count();
                
                if ($offerGalleryCount === 0 && $campaignGalleryCount > 0) {
                    $needsSync = true;
                    if (!$this->option('dry-run')) {
                        $this->syncGalleryMedia($campaign, $offer);
                    }
                    $this->line("Offer #{$offer->id}: Synced {$campaignGalleryCount} gallery images");
                }

                // check banner media
                $offerBannerCount = $offer->getMedia(MerchantOffer::MEDIA_COLLECTION_HORIZONTAL_BANNER)->count();
                $campaignBannerCount = $campaign->getMedia(MerchantOfferCampaign::MEDIA_COLLECTION_HORIZONTAL_BANNER)->count();
                
                if ($offerBannerCount === 0 && $campaignBannerCount > 0) {
                    $needsSync = true;
                    if (!$this->option('dry-run')) {
                        $this->syncBannerMedia($campaign, $offer);
                    }
                    $this->line("Offer #{$offer->id}: Synced {$campaignBannerCount} banner images");
                }

                if ($needsSync) {
                    $synced++;
                } else {
                    $skipped++;
                }

            } catch (Exception $e) {
                $this->error("Error processing offer #{$offer->id}: " . $e->getMessage());
                Log::error('Failed to sync offer media', [
                    'offer_id' => $offer->id,
                    'campaign_id' => $offer->merchant_offer_campaign_id,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);
                $errors++;
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);

        $this->info("Completed media sync:");
        
        if ($fromDate = $this->option('from') || $toDate = $this->option('to')) {
            $this->info("Date range: " . 
                ($fromDate ? $fromDate : 'beginning') . 
                " to " . 
                ($toDate ? $toDate : 'now')
            );
        }
        
        $this->table(
            ['Status', 'Count'],
            [
                ['Synced', $synced],
                ['Skipped', $skipped],
                ['Errors', $errors],
                ['Total', $totalOffers]
            ]
        );

        if ($this->option('dry-run')) {
            $this->warn('This was a dry run. No changes were made.');
        }
    }

    private function syncGalleryMedia($campaign, $offer)
    {
        $mediaItems = $campaign->getMedia(MerchantOfferCampaign::MEDIA_COLLECTION_NAME);
        foreach ($mediaItems as $mediaItem) {
            $mediaItem->copy($offer, MerchantOffer::MEDIA_COLLECTION_NAME);
        }
    }

    private function syncBannerMedia($campaign, $offer)
    {
        $mediaItems = $campaign->getMedia(MerchantOfferCampaign::MEDIA_COLLECTION_HORIZONTAL_BANNER);
        foreach ($mediaItems as $mediaItem) {
            $mediaItem->copy($offer, MerchantOffer::MEDIA_COLLECTION_HORIZONTAL_BANNER);
        }
    }
}
