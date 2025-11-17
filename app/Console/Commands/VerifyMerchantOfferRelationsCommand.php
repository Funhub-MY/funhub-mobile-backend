<?php

namespace App\Console\Commands;

use App\Models\MerchantOffer;
use App\Models\MerchantOfferCampaignSchedule;
use Illuminate\Console\Command;

class VerifyMerchantOfferRelationsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'merchant-offers:verify-relations {--fix : Fix any inconsistencies found}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Verify merchant offers created since May 15th, 2024 have proper campaign associations';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $startDate = '2024-05-15 00:00:00';
        $this->info('Starting verification of merchant offers created since ' . $startDate);

        // first, find all offers created after start date that don't have a campaign ID
        $orphanedOffers = MerchantOffer::where('created_at', '>=', $startDate)
            ->whereNull('merchant_offer_campaign_id')
            ->has('vouchers') // has vouchers generated
            ->get();

        $inconsistencies = 0;
        $fixed = 0;

        if ($orphanedOffers->isNotEmpty()) {
            $inconsistencies += $orphanedOffers->count();
            $this->warn("\nFound {$orphanedOffers->count()} offers without campaign association:");
            
            foreach ($orphanedOffers as $offer) {
                $this->line("Offer #{$offer->id}: {$offer->name}");
                $this->line("  Created at: {$offer->created_at}");
                $this->line("  SKU: {$offer->sku}");
                $this->line("  Vouchers Count: {$offer->vouchers->count()}");
                $this->line("  Schedule ID: " . ($offer->schedule_id ?? 'null'));
                
                if ($offer->schedule_id) {
                    // if the offer has a schedule, try to find the campaign through it
                    $schedule = MerchantOfferCampaignSchedule::find($offer->schedule_id);
                    if ($schedule) {
                        $this->line("  Found matching campaign #{$schedule->merchant_offer_campaign_id} through schedule #{$schedule->id}");
                        
                        if ($this->option('fix')) {
                            $offer->update(['merchant_offer_campaign_id' => $schedule->merchant_offer_campaign_id]);
                            $fixed++;
                            $this->info("  Fixed: Linked offer to campaign #{$schedule->merchant_offer_campaign_id}");
                        }
                    } else {
                        $this->warn("  Schedule #{$offer->schedule_id} not found!");
                    }
                }
                $this->newLine();
            }
        } else {
            $this->info("No offers found without campaign association.");
        }

        $this->newLine();
        $this->info("Verification completed!");
        $this->info("Total inconsistencies found: {$inconsistencies}");
        if ($this->option('fix')) {
            $this->info("Total issues fixed: {$fixed}");
        } elseif ($inconsistencies > 0) {
            $this->info("Run with --fix option to fix these issues.");
        }

        return 0;
    }
}
