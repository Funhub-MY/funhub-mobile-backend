<?php

namespace App\Console\Commands;

use App\Models\MerchantOffer;
use App\Models\MerchantOfferClaim;
use App\Models\MerchantOfferVoucher;
use Exception;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class ReleaseFailedMerchantOffers extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'merchant-offers:release';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Release all failed merchant offer after set mins';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        // get all transactions with transactionable_type == MerchantOffer and status is still pending
        // loop through each transaction and check if the created_at is more than config('app.release_offer_stock_after_min')
        // if yes, then update the status to failed and release the stock
        // if no, then do nothing
        $transactions = \App\Models\Transaction::where('transactionable_type', MerchantOffer::class)
            ->where('status', \App\Models\Transaction::STATUS_PENDING)
            ->get();

        foreach ($transactions as $transaction) {
            if ($transaction->created_at->diffInMinutes(now()) > config('app.release_offer_stock_after_min')) {
                $transaction->update([
                    'status' => \App\Models\Transaction::STATUS_FAILED,
                ]);

                Log::info('[ReleaseFailedMerchantOffers] Updated Transaction to Failed due to expired time limit', [
                    'transaction_id' => $transaction->id,
                ]);

                $offer = MerchantOffer::where('id', $transaction->transactionable_id)
                    ->first();

                $claim = MerchantOfferClaim::where('merchant_offer_id', $offer->id)
                    ->where('user_id', $transaction->user_id)
                    ->where('status', MerchantOffer::CLAIM_AWAIT_PAYMENT)
                    ->latest()
                    ->first();

                if ($claim) {
                    try {
                        if ($claim->voucher_id) {
                            $voucher = MerchantOfferVoucher::where('id', $claim->voucher_id)->first();
                            if ($voucher) {
                                $voucher->owner_by_id = null;
                                $voucher->save();
                                Log::info('Voucher released', [$voucher->toArray()]);
                            }
                        }

                        $claim->update([
                            'status' => \App\Models\MerchantOffer::CLAIM_FAILED
                        ]);
                        $offer->quantity = $offer->quantity + $claim->pivot->quantity;
                        $offer->save();

                        Log::info('[ReleaseFailedMerchantOffers] Updated Merchant Offer Claim to Failed, Stock Quantity Reverted', [
                            'transaction_id' => $transaction->id,
                            'merchant_offer_id' => $offer->id,
                        ]);
                    } catch (Exception $ex) {
                        Log::error('[ReleaseFailedMerchantOffers] Updated Merchant Offer Claim to Failed, Stock Quantity Revert Failed', [
                            'transaction_id' => $transaction->id,
                            'merchant_offer_id' => $offer->id,
                            'error' => $ex->getMessage()
                        ]);
                    }
                }
            }
        }

        return Command::SUCCESS;
    }
}
