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
        // get merchant offers where publish at is not null and status is draft and ready to be published
        $merchantOffers = \App\Models\MerchantOffer::whereNotNull('publish_at')
            ->where('status', MerchantOffer::STATUS_DRAFT)
            ->where('publish_at', '<=', Carbon::now())
            ->get();

        foreach($merchantOffers as $offer)
        {
            $this->info('Publishing merchant offer: '.$offer->id);
            // update status to published
            $offer->update(['status' => MerchantOffer::STATUS_PUBLISHED]);

            // if there is attached to campaign, also update its associated schedule
            try {
                if ($offer->schedule_id && $offer->campaign) { // also check for campaign exists
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
        }

        try {
            // ensure all published merchant offers are searchable
            MerchantOffer::where('status', MerchantOffer::STATUS_PUBLISHED)
                ->chunk(100, function ($offers) {
                    $offers->searchable();
                });
        } catch (\Exception $e) {
            Log::error('[PublishMerchantOffers] Error sync merchant offers published scout', ['error'=> $e->getMessage()]);
        }
        
        return Command::SUCCESS;
    }
}
