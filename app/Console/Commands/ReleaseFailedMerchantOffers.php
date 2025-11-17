<?php

namespace App\Console\Commands;

use App\Models\Transaction;
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
        $transactions = Transaction::where('transactionable_type', MerchantOffer::class)
            ->whereIn('status', [Transaction::STATUS_PENDING, Transaction::STATUS_FAILED])
            ->get();

        $this->info('[ReleaseFailedMerchantOffers] Total Transaction Found: ' . $transactions->count());
        $this->info('[ReleaseFailedMerchantOffers] Transaction IDS in pending/failed: ' . $transactions->pluck('id')->implode(', '));

        foreach ($transactions as $transaction) {
            $release = false;

            if ($transaction->created_at->diffInMinutes(now()) > config('app.release_offer_stock_after_min')
                && $transaction->status == Transaction::STATUS_PENDING) {
                $transaction->update([
                    'status' => Transaction::STATUS_FAILED,
                ]);
                $release = true;

                Log::info('[ReleaseFailedMerchantOffers] Updated Transaction to Failed due to expired time limit', [
                    'transaction_id' => $transaction->id,
                ]);
                $this->info('[ReleaseFailedMerchantOffers] Updated Transaction to Failed due to expired time limit, Transaction ID ' . $transaction->id);
            }

            // if already failed but claim still havent release voucher
            if ($transaction->status == Transaction::STATUS_FAILED) {
                $release = true;
            }

            $this->info('[ReleaseFailedMerchantOffers] Set to release, Transaction ID ' . $transaction->id . ' Current Status: ' . $transaction->status. ' Release: '. $release);

            if ($release) {
                $offer = MerchantOffer::where('id', $transaction->transactionable_id)
                    ->first();

                if (!$offer) {
                    // Log::error('[ReleaseFailedMerchantOffers] Merchant Offer not found', [
                    //     'transaction_id' => $transaction->id,
                    //     'merchant_offer_id' => $transaction->transactionable_id,
                    // ]);
                    $this->info('[ReleaseFailedMerchantOffers] Merchant Offer not found, Transaction ID ' . $transaction->id . ' - Offer ID: ' . $transaction->transactionable_id);
                    continue;
                } else {
                    $this->info('Offer found - '. $offer->id);
                }

                $claim = MerchantOfferClaim::where('merchant_offer_id', $offer->id)
                    ->where('user_id', $transaction->user_id)
                    ->where('transaction_no', $transaction->transaction_no)
                    ->whereIn('status', [MerchantOffer::CLAIM_AWAIT_PAYMENT, MerchantOffer::CLAIM_FAILED])
                    ->latest()
                    ->first();

                if ($claim) {
                    $this->info('Claim found - '.  json_encode($claim));

                    try {
                        if ($claim->status == MerchantOfferClaim::CLAIM_AWAIT_PAYMENT) {
                            $claim->update([
                                'status' => MerchantOffer::CLAIM_FAILED
                            ]);
                            Log::info('[ReleaseFailedMerchantOffers] Updated Merchant Offer Claim to Failed', [
                                'transaction_id' => $transaction->id,
                                'merchant_offer_id' => $offer->id,
                            ]);
                            $this->info('[ReleaseFailedMerchantOffers] Updated Merchant Offer Claim to Failed, Transaction ID ' . $transaction->id . ' - Offer ID: ' . $offer->id);
                        }

                        if ($claim->voucher_id) {
                            // first check if this voucher is successfully claimed by anyone
                            $successfulClaim = MerchantOfferClaim::where('voucher_id', $claim->voucher_id)
                                ->where('status', MerchantOfferClaim::CLAIM_SUCCESS)
                                ->first();

                            if (!$successfulClaim) {
                                // only proceed with release if no successful claims exist
                                $voucher = MerchantOfferVoucher::where('id', $claim->voucher_id)
                                    ->where('owned_by_id', $claim->user_id)
                                    ->first();

                                if ($voucher) {
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
                                Log::info('[ReleaseFailedMerchantOffers] Voucher has successful claim, skipping release', [
                                    'transaction_id' => $transaction->id,
                                    'merchant_offer_id' => $offer->id,
                                    'voucher_id' => $claim->voucher_id,
                                    'successful_claim_id' => $successfulClaim->id
                                ]);
                                $this->info('[ReleaseFailedMerchantOffers] Voucher has successful claim, skipping release. Transaction ID ' . $transaction->id);
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
