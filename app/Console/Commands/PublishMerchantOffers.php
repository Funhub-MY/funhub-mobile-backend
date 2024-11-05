<?php

namespace App\Console\Commands;

use App\Events\MerchantOfferPublished;
use App\Models\MerchantOffer;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

use Illuminate\Support\Carbon; // (Kenneth)


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
        // get merchant offers where publish at is not null and status is draft
        $merchantOffers = \App\Models\MerchantOffer::whereNotNull('publish_at')
            ->where('status', MerchantOffer::STATUS_DRAFT)
            ->where('publish_at', '<=', Carbon::now()) // (Kenneth)
            ->get();

        foreach($merchantOffers as $offer)
        {
            $this->info('Publishing merchant offer: '.$offer->id);
            // // if publish at is less than or equal to current time
            // if ($offer->publish_at <= now()) {
                // update status to published 
                $offer->update(['status' => MerchantOffer::STATUS_PUBLISHED]);

                // if there is attached to campaign, also update its associated schedule
                try {
                    if ($offer->schedule_id) {
                        $offer->campaign->schedules()->where('id', $offer->schedule_id)
                            ->update(['status' => MerchantOffer::STATUS_PUBLISHED]);
                    }
                } catch (\Exception $e) {
                    Log::error('[PublishMerchantOffers] Error updating schedule status', ['error' => $e->getMessage()]);
                }

                // fire event MerchantOfferPublished
                event(new MerchantOfferPublished($offer));

                Log::info('[PublishMerchantOffers] Merchant offer published', ['offer_id' => $offer->id]);
                $this->info('Merchant offer published: '.$offer->id);
            // } else {
            //     $this->info('Merchant offer publish at is not less than or equal to current time: '.$offer->id);
            // }
        }

         // Update batch (Kenneth)
        // $merchantOffers = \App\Models\MerchantOffer::whereNotNull('publish_at')
        //     ->where('status', MerchantOffer::STATUS_DRAFT)
        //     ->where('publish_at', '<=', Carbon::now())
        //     ->update(['status' => MerchantOffer::STATUS_PUBLISHED]);

         
        return Command::SUCCESS;
    }

    /* 
        LOGIC FOR THIS CAMPAIGN 
        Admin will create the campaign and the campaign will create the multiple merchant offer based on the schedule to release the vouchers. Each of the offer allow to modify the pricing and information.

        The purpose of this design may cause of different timeframe will have different required information.

        Suggestion : 
            1) Can the vouchers is based on campaign and not offer.
            2) With this, the campaign can hold all the total amount of the voucher and offer quantity is limit the maximum to purchase within the timeframe
            3) Once the user purchase the voucher, will update the voucher under the offer, so from here able to keep track the redeem terms based on the offer schedule.


    */
}
