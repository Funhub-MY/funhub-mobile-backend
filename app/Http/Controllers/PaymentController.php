<?php

namespace App\Http\Controllers;

use App\Models\MerchantOffer;
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
        if (!$request->has('result') || !$request->has('secureHash') || !$request->has('mid') || !$request->has('responseCode') || !$request->has('authCode') || !$request->has('amt') || !$request->has('invno') || !$request->has('responseDesc') || !$request->has('tranDate') || !$request->has('paymentType') || !$request->has('securehash2') || !$request->has('mpay_ref_no')) {
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
                    'mpay_ref_no' => $request->has('mpay_ref_no')
                ],
                'request' => request()->all()
            ]);
            return 'Transaction Failed';
        }

        // get transaction record via $request->invno
        $transaction = \App\Models\Transaction::where('transaction_no', request()->invno)->first();

        if ($transaction) {
            // has transaction validate secure hash first from mpay
            if (!$this->validateSecureHash(
                config('services.mpay.mid'),
                $request->responseCode,
                $request->authCode,
                $request->invno,
                $transaction->amount
            )) {
                Log::error('Payment return failed', [
                    'error' => 'Secure Hash validation failed',
                    'request' => request()->all()
                ]);
                return 'Transaction Failed - Secure Hash Validation Failed';
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
                }

                return 'Transaction Success';
            } else if ($request->responseCode == 'PE') { // pending
                return 'Transaction Still Pending';
            } else { // failed
                $transaction->update([
                    'status' => \App\Models\Transaction::STATUS_FAILED,
                    'gateway_transaction_id' => $request->authCode,
                ]);
                if ($transaction->transactionable_type == MerchantOffer::class) {
                    $this->updateMerchantOfferTransaction($request, $transaction);
                }

                return 'Transaction Failed';
            }
        } else {
            Log::error('Payment return failed', [
                'error' => 'Transaction not found',
                'request' => request()->all()
            ]);
            return 'Transaction Failed';
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
        if ($request->responseCode == 0 || $request->responseCode == '0') {
            // update merchant claim by user
            MerchantOffer::where('id', $transaction->transactionable_id)->claims()->updateExistingPivot($transaction->user_id, [
                'status' => \App\Models\MerchantOffer::CLAIM_SUCCESS
            ]);

            Log::info('Updated Merchant Offer Claim to Success', [
                'transaction' => $transaction->toArray(),
                'merchant_offer' => $transaction->transactionable->toArray(),
            ]);

        } else if ($request->responseCode == 'PE') {
            // still pending
        } else {
            // failed
            MerchantOffer::where('id', $transaction->transactionable_id)->claims()->updateExistingPivot($transaction->user_id, [
                'status' => \App\Models\MerchantOffer::CLAIM_FAILED
            ]);

            // get current claims where pivot.user_id == $transaction->user_id and get the quantity in claims
            // add back in MerchantOffer
            $claim = MerchantOffer::where('id', $transaction->transactionable_id)->claims()->wherePivot('user_id', $transaction->user_id)->first();
            if ($claim) {
                try {
                    $merchantOffer = MerchantOffer::find($transaction->transactionable_id);
                    $merchantOffer->quantity = $merchantOffer->quantity + $claim->pivot->quantity;
                    $merchantOffer->save();

                    Log::info('Updated Merchant Offer Claim to Failed, Stock Quantity Reverted', [
                        'transaction' => $transaction->toArray(),
                        'merchant_offer' => $transaction->transactionable->toArray(),
                    ]);
                } catch (Exception $ex) {
                    Log::error('Updated Merchant Offer Claim to Failed, Stock Quantity Revert Failed', [
                        'transaction' => $transaction->toArray(),
                        'merchant_offer' => $transaction->transactionable->toArray(),
                        'error' => $ex->getMessage()
                    ]);
                }
            }
        }
    }
}
