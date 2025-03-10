<?php

namespace App\Console\Commands;

use App\Models\Merchant;
use App\Models\MerchantOffer;
use Illuminate\Console\Command;
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
        // archive sold out offers at midnight
        $soldOutOffers = MerchantOffer::published()
            ->doesntHave('unclaimedVouchers') // all bought
            ->available()
            ->get();

        foreach ($soldOutOffers as $offer) {
            $offer->update([
                'status' => MerchantOffer::STATUS_ARCHIVED,
            ]);
            
            // Also update the associated schedule status if it exists
            if ($offer->schedule_id) {
                \App\Models\MerchantOfferCampaignSchedule::where('id', $offer->schedule_id)
                    ->update(['status' => \App\Models\MerchantOfferCampaignSchedule::STATUS_ARCHIVED]);
                
                Log::info('[AutoArchiveMerchantOffer] Also archived Schedule: ' . $offer->schedule_id . ' for Offer: ' . $offer->id);
            }

            $this->info('[AutoArchiveMerchantOffer] Archived Offer: ' . $offer->id. ' sold out');
            Log::info('[AutoArchiveMerchantOffer] Archived Offer: ' . $offer->id. ' sold out');
        }

        // archive all offers that are past available_until
        $pastAvailableUntilOffers = MerchantOffer::published()
            ->where('available_until', '<', now())
            ->get();

        foreach ($pastAvailableUntilOffers as $offer) {
            $offer->update([
                'status' => MerchantOffer::STATUS_ARCHIVED,
            ]);
            
            // Also update the associated schedule status if it exists
            if ($offer->schedule_id) {
                \App\Models\MerchantOfferCampaignSchedule::where('id', $offer->schedule_id)
                    ->update(['status' => \App\Models\MerchantOfferCampaignSchedule::STATUS_ARCHIVED]);
                
                Log::info('[AutoArchiveMerchantOffer] Also archived Schedule: ' . $offer->schedule_id . ' for Offer: ' . $offer->id);
            }

            $this->info('[AutoArchiveMerchantOffer] Archived Offer: ' . $offer->id. ' past available_until');
            Log::info('[AutoArchiveMerchantOffer] Archived Offer: ' . $offer->id. ' past available_until');
        }

        return Command::SUCCESS;
    }
}
