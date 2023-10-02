<?php

namespace App\Http\Controllers;

use App\Models\MerchantOffer;
use App\Models\MerchantOfferClaim;
use App\Models\MerchantOfferVoucher;
use App\Models\Product;
use App\Notifications\OfferClaimed;
use App\Services\Mpay;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class PaymentController extends Controller
{
    protected $gateway;

    public function __construct()
    {
        $this->gateway = new Mpay(
            config('services.mpay.mid'),
            config('services.mpay.hash_key')
        );
    }

    /**
     * Payment Return from Gateway
     *
     * @param Request $request
     * @return string
     */
    public function paymentReturn(Request $request)
    {
        Log::info('Payment return', [
            'request' => request()->all()
        ]);

        // check if request has {"result":null,"secureHash":"E8EF785622C418B75B4B6F3ED778729F01991D4FB04E681AE5E088895F530394","mid":"7860","responseCode":"XC","maskedPAN":null,"authCode":null,"amt":"150.00","invno":"2308106HLQ8S","responseDesc":"Seller Exchange Encryption Error","tranDate":"2023-08-09 12:05:04","paymentType":"FPX-B2C","securehash2":"E8EF785622C418B75B4B6F3ED778729F01991D4FB04E681AE5E088895F530394","mpay_ref_no":"REF007860021002"}
        if (!$request->has('result') || !$request->has('secureHash') || !$request->has('mid') || !$request->has('responseCode') || !$request->has('authCode') || !$request->has('amt') || !$request->has('invno') || !$request->has('responseDesc') || !$request->has('tranDate') || !$request->has('paymentType') || !$request->has('securehash2')) {
            Log::error('Payment return failed', [
                'error' => 'Missingh parameter from request',
                'missing' => [
                    'result' => $request->has('result'),
                    'secureHash' => $request->has('secureHash'),
                    'mid' => $request->has('mid'),
                    'responseCode' => $request->has('responseCode'),
                    'authCode' => $request->has('authCode'),
                    'amt' => $request->has('amt'),
                    'invno' => $request->has('invno'),
                    'responseDesc' => $request->has('responseDesc'),
                    'tranDate' => $request->has('tranDate'),
                    'paymentType' => $request->has('paymentType'),
                    'securehash2' => $request->has('securehash2'),
                    // 'mpay_ref_no' => $request->has('mpay_ref_no')
                ],
                'request' => request()->all()
            ]);
            return 'Transaction Failed';
        }

        // get transaction record via $request->invno
        $transaction = \App\Models\Transaction::where('transaction_no', request()->invno)->first();

        if ($transaction) {
            // initiate mpay instance based on transaction type
            $this->gateway = new Mpay(
                config('services.mpay.mid'),
                config('services.mpay.hash_key'),
                ($transaction->payment_method) ? $transaction->payment_method : false
            );

            // has transaction validate secure hash first from mpay
            if (!$this->validateSecureHash(
                $request->mid,
                $request->responseCode,
                $request->authCode,
                $request->invno,
                $request->amt
            )) {
                Log::error('Payment return failed', [
                    'error' => 'Secure Hash validation failed',
                    'request' => request()->all()
                ]);
                return view('payment-return', [
                    'message' => 'Transaction Failed - Hash Validation Failed',
                    'transaction_id' => $transaction->id,
                    'success' => false
                ]);
            }
            // check response code status

            if ($request->responseCode == 0 || $request->responseCode == '0') { // success
                  // update transaction status to success first with gateway transaction id
                  $transaction->update([
                    'status' => \App\Models\Transaction::STATUS_SUCCESS,
                    'gateway_transaction_id' => $request->mpay_ref_no,
                ]);
                if ($transaction->transactionable_type == MerchantOffer::class) {
                    $this->updateMerchantOfferTransaction($request, $transaction);
                } else if ($transaction->transactionable_type == Product::class) {
                    $this->updateProductTransaction($request, $transaction);
                }

                // return with js
                // window.flutter_inappwebview.callHandler('passData', {'someKey': 'someValue'});
                return view('payment-return', [
                    'message' => 'Transaction Success',
                    'transaction_id' => $transaction->id,
                    'success' => true
                ]);
            } else if ($request->responseCode == 'PE') { // pending
                return 'Transaction Still Pending';
            } else { // failed
                $transaction->update([
                    'status' => \App\Models\Transaction::STATUS_FAILED,
                    'gateway_transaction_id' => $request->mpay_ref_no,
                ]);
                if ($transaction->transactionable_type == MerchantOffer::class) {
                    $this->updateMerchantOfferTransaction($request, $transaction);
                } else if ($transaction->transactionable_type == Product::class) {
                    $this->updateProductTransaction($request, $transaction);
                }

                return view('payment-return', [
                    'message' => 'Transaction Failed',
                    'transaction_id' => $transaction->id,
                    'success' => false
                ]);
            }
        } else {
            Log::error('Payment return failed', [
                'error' => 'Transaction not found',
                'request' => request()->all()
            ]);

            return view('payment-return', [
                'message' => 'Transaction Failed - No transaction',
                'transaction_id' => null,
                'success' => false
            ]);
        }

    }

    protected function updateProductTransaction($request, $transaction)
    {
        $product = Product::where('id', $transaction->transactionable_id)->first();

        if (!$product) {
            Log::error('Payment return failed', [
                'error' => 'Product not found',
                'request' => request()->all()
            ]);
            return false;
        }

        // get product reward if any
        if ($product) {
            if ($request->responseCode == 0 || $request->responseCode == '0') {

                $reward = $product->rewards()->first();
                if ($reward) {
                    $pointService = new \App\Services\PointService($transaction->user);

                    // credit user
                    $pointService->credit(
                        $reward,
                        $transaction->user,
                        $reward->pivot->quantity,
                        'Gift Card Purchase',
                        $transaction->transaction_no
                    );

                } else {
                    // no reward found
                    Log::error('Payment return success but no product reward', [
                        'error' => 'Product reward not found',
                        'request' => request()->all()
                    ]);
                }
            } else if ($request->responseCode == 'PE') {
                // still pending
                Log::info('Updated Product Transaction Still Pending', [
                    'user_id' => $transaction->user_id,
                    'transaction_id' => $transaction->id,
                    'product_id' => $transaction->transactionable_id,
                ]);
            } else {
                // failed
                Log::info('Updated Product Transaction to Failed', [
                    'user_id' => $transaction->user_id,
                    'transaction_id' => $transaction->id,
                    'product_id' => $transaction->transactionable_id,
                ]);
            }
        }
    }

    /**
     * Validate secure hash
     *
     * @param string $mid
     * @param string $responseCode
     * @param string $authCode
     * @param string $invoice_no
     * @param float|string $amount
     * @return boolean
     */
    protected function validateSecureHash($mid, $responseCode, $authCode, $invoice_no, $amount)
    {
        // $amount = str_pad(number_format($amount, 2, '', ''), 12, '0', STR_PAD_LEFT);

        $secureHash = $this->gateway->generateHashForResponse($mid, $responseCode, $authCode, $invoice_no, $amount);
        return $secureHash == request()->securehash2;
    }

    /**
     * Update Merchant Offer Transaction
     *
     * @param Request $request
     * @param Transaction $transaction
     * @return void
     */
    protected function updateMerchantOfferTransaction($request, $transaction)
    {
        // update merchant claim by user
        $merchantOffer = MerchantOffer::where('id', $transaction->transactionable_id)->first();

        if (!$merchantOffer) {
            Log::error('Payment return failed', [
                'error' => 'Merchant Offer not found',
                'request' => request()->all()
            ]);
            return 'Transaction Failed - Invalid Transaction ID';
        }

        // get claim
        $claim = MerchantOfferClaim::where('merchant_offer_id', $merchantOffer->id)
            ->where('user_id', $transaction->user_id)
            ->where('status', MerchantOffer::CLAIM_AWAIT_PAYMENT)
            ->latest()
            ->first();

        if ($request->responseCode == 0 || $request->responseCode == '0') {
            $claim->update([
                'status' => \App\Models\MerchantOffer::CLAIM_SUCCESS
            ]);

            Log::info('Updated Merchant Offer Claim to Success', [
                'transaction_id' => $transaction->id,
                'merchant_offer_id' => $transaction->transactionable_id,
            ]);

            try {
                // notify
                $transaction->user->notify(new OfferClaimed($merchantOffer, $transaction->user, 'fiat', $transaction->amount));
            } catch (Exception $ex) {
                Log::error('Failed to send notification', [
                    'error' => $ex->getMessage(),
                    'transaction_id' => $transaction->id,
                    'merchant_offer_id' => $transaction->transactionable_id,
                    'user_id' => $transaction->user_id,
                ]);
            }
        } else if ($request->responseCode == 'PE') {
            // still pending
            Log::info('Updated Merchant Offer Claim Still Pending', [
                'user_id' => $transaction->user_id,
                'transaction_id' => $transaction->id,
                'merchant_offer_id' => $transaction->transactionable_id,
            ]);
        } else {
            // failed
            $claim->update([
                'status' => \App\Models\MerchantOffer::CLAIM_FAILED
            ]);
            if ($claim) {
                try {
                    $merchantOffer->quantity = $merchantOffer->quantity + $claim->pivot->quantity;
                    $merchantOffer->save();

                    // release voucher
                    $voucher_id = $claim->voucher_id;
                    if ($voucher_id) {
                        $voucher = MerchantOfferVoucher::where('id', $voucher_id)
                            ->where('owned_by_id', $claim->user_id)
                            ->first();
                        if ($voucher) {
                            $voucher->owned_by_id = null;
                            $voucher->save();
                            Log::info('[MerchantOfferController] Voucher released', [$voucher->toArray()]);
                        }
                    }

                    Log::info('Updated Merchant Offer Claim to Failed, Stock Quantity Reverted', [
                        'transaction_id' => $transaction->id,
                        'merchant_offer_id' => $transaction->transactionable_id,
                    ]);
                } catch (Exception $ex) {
                    Log::error('Updated Merchant Offer Claim to Failed, Stock Quantity Revert Failed', [
                        'transaction_id' => $transaction->id,
                        'merchant_offer_id' => $transaction->transactionable_id,
                        'error' => $ex->getMessage()
                    ]);
                }
            }
        }
    }
}
