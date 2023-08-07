<?php

namespace App\Console\Commands;

use App\Models\MerchantOffer;
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
                    'transaction' => $transaction->toArray(),
                ]);

                $claim = MerchantOffer::where('id', $transaction->transactionable_id)->claims()->wherePivot('user_id', $transaction->user_id)->first();
                if ($claim) {
                    try {
                        $merchantOffer = MerchantOffer::find($transaction->transactionable_id);
                        $merchantOffer->quantity = $merchantOffer->quantity + $claim->pivot->quantity;
                        $merchantOffer->save();
    
                        Log::info('[ReleaseFailedMerchantOffers] Updated Merchant Offer Claim to Failed, Stock Quantity Reverted', [
                            'transaction' => $transaction->toArray(),
                            'merchant_offer' => $transaction->transactionable->toArray(),
                        ]);
                    } catch (Exception $ex) {
                        Log::error('[ReleaseFailedMerchantOffers] Updated Merchant Offer Claim to Failed, Stock Quantity Revert Failed', [
                            'transaction' => $transaction->toArray(),
                            'merchant_offer' => $transaction->transactionable->toArray(),
                            'error' => $ex->getMessage()
                        ]);
                    }
                }
            }
        }

        return Command::SUCCESS;
    }
}
