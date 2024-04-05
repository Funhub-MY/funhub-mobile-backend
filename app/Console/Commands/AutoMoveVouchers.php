<?php

namespace App\Console\Commands;

use App\Models\MerchantOffer;
use App\Models\MerchantOfferVoucherMovement;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class AutoMoveVouchers extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'merchant-offers:auto-move-vouchers-unsold';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Automactically move unsold vouchers';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        // get all merchant offers published that has campaign_id
        $offers = MerchantOffer::has('campaign')
            ->where('status', MerchantOffer::STATUS_PUBLISHED)
            ->with('campaign')
            ->get();

        $this->info('[AutoMoveVouchers] Total Merchant Offers Found: ' . $offers->count());

        // go through each offer, check if the available_until is passed and still have unclaimedVouchers, move the unclaimed voucher to upcoming offer under same campaign id
        foreach ($offers as $offer) {
            $this->info('Checking: ' . $offer->id . ' ' . $offer->name . ' ' . $offer->available_at . '->' .$offer->available_until . ' - Unclaimed: ' . $offer->unclaimedVouchers()->count());
            // if no auto move vouchers turned on it will skip
            if ($offer->campaign->auto_move_vouchers == false) {
                $this->info('[AutoMoveVouchers] Auto Move Vouchers is turned off for this campaign, skipping');
                continue;
            }

            // already past available_until and still have unclaimed(unsold) vouchers
            // move to upcoming merchant offer under same campaign
            if (Carbon::parse($offer->available_until)->isPast() && $offer->unclaimedVouchers()->count() > 0) {
                $upcomingOffer = MerchantOffer::whereHas('campaign', function ($q) use ($offer) {
                    $q->where('merchant_offer_campaigns.id', $offer->campaign->id);
                })->where('id', '!=', $offer->id)
                    ->where('available_at', '>', now())
                    ->where('status', MerchantOffer::STATUS_PUBLISHED)
                    ->orderBy('available_at', 'asc')
                    ->first();

                if ($upcomingOffer) {
                    $unclaimedVouchers = $offer->unclaimedVouchers()->get();
                    $unclaimedVouchers->each(function ($voucher) use ($upcomingOffer, $offer) {

                        // create voucher movements
                           MerchantOfferVoucherMovement::create([
                            'from_merchant_offer_id' => $offer->id,
                            'to_merchant_offer_id' => $upcomingOffer->id,
                            'voucher_id' => $voucher->id,
                            'user_id' => $offer->user_id,
                            'remarks' => 'Auto Moved',
                        ]);

                        // moved voucher to upcoming offer
                        $voucher->update([
                            'merchant_offer_id' => $upcomingOffer->id
                        ]);

                        Log::info('[AutoMoveVouchers] Moved ', [
                            'voucher_id' => $voucher->id,
                            'code' => $voucher->code,
                            'from' => $offer->id,
                            'to' => $upcomingOffer->id
                        ]);

                        $this->info('[AutoMoveVouchers] Moved Voucher ID ' . $voucher->id . ' from ' . $offer->id . ' to ' . $upcomingOffer->id);
                    });
                }

                // if dont have, no action, vouchers stock remained unsold.
            }
        }

        return Command::SUCCESS;
    }
}
