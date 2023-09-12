<?php

namespace App\Console\Commands;

use App\Models\MerchantOfferVoucher;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class PopulateMerchantOfferWithVouchers extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'merchant-offers:populate-vouchers';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        // find merchant offers with quantity but zero vouchers as voucher represent per quantity of stock
        $offers = \App\Models\MerchantOffer::where('quantity', '>', 0)
            ->whereDoesntHave('vouchers')
            ->get();

        foreach ($offers as $offer) {
            // create vouchers based on quantity
            for($i = 0; $i < $offer->quantity; $i++) {
                $voucher = MerchantOfferVoucher::create([
                    'merchant_offer_id' => $offer->id,
                    'code' => MerchantOfferVoucher::generateCode(),
                ]);
                Log::info('[PopulateMerchantOfferWithVouchers] Created voucher for merchant offer', [
                    'merchant_offer_id' => $offer->id,
                    'voucher_id' => $voucher->id,
                ]);
                $this->info('1 voucher for merchant offer '.$offer->id);
            }
        }
        
        return Command::SUCCESS;
    }
}
