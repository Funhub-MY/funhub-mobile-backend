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
            ->whereIn('status', [\App\Models\Transaction::STATUS_PENDING, \App\Models\Transaction::STATUS_FAILED])
            ->get();

        $this->info('[ReleaseFailedMerchantOffers] Total Transaction Found: ' . $transactions->count());
        $this->info('[ReleaseFailedMerchantOffers] Transaction IDS in pending/failed: ' . $transactions->pluck('id')->implode(', '));

        foreach ($transactions as $transaction) {
            $release = false;

            if ($transaction->created_at->diffInMinutes(now()) > config('app.release_offer_stock_after_min')
                && $transaction->status == \App\Models\Transaction::STATUS_PENDING) {
                $transaction->update([
                    'status' => \App\Models\Transaction::STATUS_FAILED,
                ]);
                $release = true;

                Log::info('[ReleaseFailedMerchantOffers] Updated Transaction to Failed due to expired time limit', [
                    'transaction_id' => $transaction->id,
                ]);
                $this->info('[ReleaseFailedMerchantOffers] Updated Transaction to Failed due to expired time limit, Transaction ID ' . $transaction->id);
            }

            // if already failed but claim still havent release voucher
            if ($transaction->status == \App\Models\Transaction::STATUS_FAILED) {
                $release = true;
            }

            $this->info('[ReleaseFailedMerchantOffers] Set to release, Transaction ID ' . $transaction->id . ' Current Status: ' . $transaction->status. ' Release: '. $release);

            if ($release) {
                $offer = MerchantOffer::where('id', $transaction->transactionable_id)
                    ->first();

                $this->info('Offer found - '. $offer->id);

                if (!$offer) {
                    // Log::error('[ReleaseFailedMerchantOffers] Merchant Offer not found', [
                    //     'transaction_id' => $transaction->id,
                    //     'merchant_offer_id' => $transaction->transactionable_id,
                    // ]);
                    $this->info('[ReleaseFailedMerchantOffers] Merchant Offer not found, Transaction ID ' . $transaction->id . ' - Offer ID: ' . $transaction->transactionable_id);
                    continue;
                }

                $claim = MerchantOfferClaim::where('merchant_offer_id', $offer->id)
                    ->where('user_id', $transaction->user_id)
                    ->whereIn('status', [MerchantOffer::CLAIM_AWAIT_PAYMENT, MerchantOffer::CLAIM_FAILED])
                    ->latest()
                    ->first();

                if ($claim) {
                    $this->info('Claim found - '.  json_encode($claim));

                    try {
                        if ($claim->status == MerchantOfferClaim::CLAIM_AWAIT_PAYMENT) {
                            $claim->update([
                                'status' => \App\Models\MerchantOffer::CLAIM_FAILED
                            ]);
                            Log::info('[ReleaseFailedMerchantOffers] Updated Merchant Offer Claim to Failed', [
                                'transaction_id' => $transaction->id,
                                'merchant_offer_id' => $offer->id,
                            ]);
                            $this->info('[ReleaseFailedMerchantOffers] Updated Merchant Offer Claim to Failed, Transaction ID ' . $transaction->id . ' - Offer ID: ' . $offer->id);
                        }

                        if ($claim->voucher_id) {
                            $voucher = MerchantOfferVoucher::where('id', $claim->voucher_id)
                                ->where('owned_by_id', $claim->user_id)
                                ->first();
                            if ($voucher) { // maker sure is owned_by_user is not null is by the claim user
                                $voucher->owned_by_id = null;
                                $voucher->save();

                                $releaseQuantity = $claim->pivot->quantity;

                                $offer->quantity = $offer->quantity + $releaseQuantity;
                                $offer->save();

                                Log::info('[ReleaseFailedMerchantOffers] Stock Quantity Reverted, Voucher Released', [
                                    'transaction_id' => $transaction->id,
                                    'merchant_offer_id' => $offer->id,
                                    'voucher_id' => $voucher->id,
                                    'quantity' => $releaseQuantity,
                                ]);
                                $this->info('[ReleaseFailedMerchantOffers] Stock Quantity Reverted, Voucher Released, Transaction ID ' . $transaction->id . ' - Offer ID: ' . $offer->id);
                            }
                        } else {
                            // dont have voucher id to release
                            $this->info('[ReleaseFailedMerchantOffers] No voucher id to release, Transaction ID ' . $transaction->id . ' - Offer ID: ' . $offer->id);
                        }
                    } catch (Exception $ex) {
                        Log::error('[ReleaseFailedMerchantOffers] Updated Merchant Offer Claim to Failed, Stock Quantity Revert Failed', [
                            'transaction_id' => $transaction->id,
                            'merchant_offer_id' => $offer->id,
                            'error' => $ex->getMessage()
                        ]);
                        $this->info('[ReleaseFailedMerchantOffers] Updated Merchant Offer Claim to Failed, Stock Quantity Revert Failed, Transaction ID ' . $transaction->id . ' - Offer ID: ' . $offer->id);
                    }
                } else {
                    $this->info('[ReleaseFailedMerchantOffers] Merchant Offer Claim not found, Transaction ID ' . $transaction->id . ' - Offer ID: ' . $offer->id);
                }
            } // end of release

        }

        return Command::SUCCESS;
    }
}
