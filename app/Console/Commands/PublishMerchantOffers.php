<?php

namespace App\Console\Commands;

use App\Models\MerchantOffer;
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
        // get merchant offers where publish at is not null and status is draft
        $merchantOffers = \App\Models\MerchantOffer::whereNotNull('publish_at')
            ->where('status', MerchantOffer::STATUS_DRAFT)
            ->get();

        foreach($merchantOffers as $offer)
        {
            $this->info('Publishing merchant offer: '.$offer->id);
            // if publish at is less than or equal to current time
            if ($offer->publish_at <= now()) {
                // update status to published
                $offer->update(['status' => MerchantOffer::STATUS_PUBLISHED]);
                Log::info('[PublishMerchantOffers] Merchant offer published', ['offer_id' => $offer->id]);
                $this->info('Merchant offer published: '.$offer->id);
            } else {
                $this->info('Merchant offer publish at is not less than or equal to current time: '.$offer->id);
            }
        }
        return Command::SUCCESS;
    }
}
