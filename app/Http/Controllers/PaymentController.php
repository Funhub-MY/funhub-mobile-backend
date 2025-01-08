<?php

namespace App\Http\Controllers;

use Exception;
use App\Services\Mpay;
use App\Models\Product;
use Illuminate\Http\Request;
use App\Models\MerchantOffer;
use App\Models\MerchantOfferClaim;
use App\Notifications\OfferClaimed;
use Illuminate\Support\Facades\Log;
use App\Models\MerchantOfferVoucher;
use App\Models\Transaction;
use App\Models\User;
use App\Models\UserCard;
use App\Notifications\PurchasedGiftCardNotification;
use App\Notifications\PurchasedOfferNotification;
use App\Services\PointService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Notification;

class PaymentController extends Controller
{
    protected $gateway;
    protected $checkout_secret;

    protected $smsService;

    public function __construct()
    {
        $this->gateway = new Mpay(
            config('services.mpay.mid'),
            config('services.mpay.hash_key')
        );

        $this->checkout_secret = config('app.funhub_checkout_secret');

        $this->smsService = new \App\Services\Sms(
            [
                'url' => config('services.byteplus.sms_url'),
                'username' => config('services.byteplus.sms_account'),
                'password' => config('services.byteplus.sms_password'),
            ],
            [
                'api_url' => config('services.movider.api_url'),
                'key' => config('services.movider.key'),
                'secret' => config('services.movider.secret'),
            ]
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
        Log::info('Payment return/callback', [
            'headers' => request()->header(),
            'request' => request()->all()
        ]);

        // check if request has {"result":null,"secureHash":"E8EF785622C418B75B4B6F3ED778729F01991D4FB04E681AE5E088895F530394","mid":"7860","responseCode":"XC","maskedPAN":null,"authCode":null,"amt":"150.00","invno":"2308106HLQ8S","responseDesc":"Seller Exchange Encryption Error","tranDate":"2023-08-09 12:05:04","paymentType":"FPX-B2C","securehash2":"E8EF785622C418B75B4B6F3ED778729F01991D4FB04E681AE5E088895F530394","mpay_ref_no":"REF007860021002"}
        if (!$request->has('mid') || !$request->has('responseCode') || !$request->has('authCode') || !$request->has('amt') || !$request->has('invno') || !$request->has('responseDesc') || !$request->has('tranDate') || !$request->has('paymentType') || !$request->has('securehash2')) {
            Log::error('Payment return failed', [
                'error' => 'Missing parameter from request',
                'missing' => [
                    // 'result' => $request->has('result'),
                    // 'secureHash' => $request->has('secureHash'),
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

            return view('payment-return', [
                'message' => 'Transaction Failed [1]',
                'transaction_id' => null,
                'success' => false
            ]);
        }

        // get transaction record via $request->invno
        $transaction = \App\Models\Transaction::where('transaction_no', request()->invno)->first();

        Log::info('Transaction found', [
            'transaction' => $transaction,
            'request' => request()->all()
        ]);

        if ($transaction) {
            // initiate mpay instance based on transaction type
            $this->gateway = new Mpay(
                config('services.mpay.mid'),
                config('services.mpay.hash_key'),
                ($transaction->payment_method) ? $transaction->payment_method : false
            );

            // has transaction validate secure hash first from mpay
            if (
                !$this->validateSecureHash(
                    $request->mid,
                    $request->responseCode,
                    $request->authCode,
                    $request->invno,
                    $request->amt
                )
            ) {
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
            // check if transaction already a success or failed
            if ($transaction->status != \App\Models\Transaction::STATUS_PENDING) {
                Log::info('Payment return/callback already processed', [
                    'error' => 'Transaction already processed',
                    'request' => request()->all()
                ]);

                if ($transaction->status == \App\Models\Transaction::STATUS_SUCCESS) {
                    // SUCCESS
                    $offer_name = null;
                    $offer_id = null;
                    $claim_id = null;
                    $redemption_start_date = null;
                    $redemption_end_date = null;
                    if ($transaction->transactionable_type == \App\Models\MerchantOffer::class) {
                        $merchantOffer = MerchantOffer::where('id', $transaction->transactionable_id)->first();
                        $offer_name = $merchantOffer ? $merchantOffer->name : null;
                        $offer_id = $merchantOffer ? $merchantOffer->id : null;

                        $claim = MerchantOfferClaim::where('merchant_offer_id', $transaction->transactionable_id)
                            ->where('user_id', $transaction->user_id)
                            ->latest()
                            ->first();

                        if ($claim) {
                            $claim_id = $claim->id;
                            $redemption_start_date = $claim->created_at;

                            if (isset($claim->merchantOffer)) {
                                $redemption_end_date = $claim->created_at->addDays($claim->merchantOffer->expiry_days)->endOfDay();
                            } else {
                                $redemption_end_date = $claim->created_at->endOfDay(); // Default to one-day expiry if not set
                            }
                        }
                    }

                    $params = [
                        'message' => 'Transaction Success',
                        'transaction_id' => $transaction->id,
                        'transaction_no' => $transaction->transaction_no,
                        'offer_name' => $offer_name,
                        'offer_id' => $offer_id,
                        'offer_claim_id' => $claim_id,
                        'redemption_start_date' => $redemption_start_date ? $redemption_start_date->toISOString() : null,
                        'redemption_end_date' => $redemption_end_date ? $redemption_end_date->toISOString() : null,
                        'success' => true,
                    ];

                    if ($transaction->channel === 'app') {
                        return view('payment-return', $params);
                    } else if ($transaction->channel === 'funhub_web') {
                        // redirect to FUNHUB_WEB_LINK
                        // encode all params above with a hash string agreed by both ends
                        $hash_string = hash_hmac('sha256', $transaction->transaction_no, config('app.funhub_web_hash_secret'));
                        return redirect(config('app.funhub_web_link') . '?data=' . urlencode(json_encode($params)) . '&hash=' . $hash_string);
                    }
                } else {
                    // PENDING
                    if ($request->responseCode == 'PE') {
                        if ($transaction->channel === 'app') {
                            return 'Transaction Still Pending';
                        } else if ($transaction->channel === 'funhub_web') {
                            return redirect(config('app.funhub_web_link') . '?data=' . urlencode(json_encode(['message' => 'Transaction Still Pending', 'success' => false])));
                        }
                    } else {
                        // FAILURE
                        $params = [
                            'message' => 'Transaction Failed - Already Processed',
                            'transaction_id' => $transaction->id,
                            'transaction_no' => $transaction->transaction_no,
                            'success' => false
                        ];
                        if ($transaction->channel === 'app') {
                            return view('payment-return', $params);
                        } else if ($transaction->channel === 'funhub_web') {
                            $hash_string = hash_hmac('sha256', $transaction->transaction_no, config('app.funhub_web_hash_secret'));
                            return redirect(config('app.funhub_web_link') . '?data=' . urlencode(json_encode($params)) . '&hash=' . $hash_string);
                        }
                    }
                }
            }

            // ===================== SUCCESS
            if ($request->responseCode == 0 || $request->responseCode == '0') { // success

                $transactionUpdateData = [
                    'status' => \App\Models\Transaction::STATUS_SUCCESS,
                    'gateway_transaction_id' => ($request->has('mpay_ref_no')) ? $request->mpay_ref_no : $request->authCode,
				];

                // update transaction status to success first with gateway transaction id
                $transaction->update($transactionUpdateData);

                $redemption_start_date = null;
                $redemption_end_date = null;

                if ($transaction->transactionable_type == MerchantOffer::class) {
                    $this->updateMerchantOfferTransaction($request, $transaction);
                    $claim = MerchantOfferClaim::where('merchant_offer_id', $transaction->transactionable_id)
                        ->where('user_id', $transaction->user_id)
                        ->latest()
                        ->first();

                    if ($claim) {
                        // redemption dates is claim created_at + offer expiry_days
                        $redemption_start_date = $claim->created_at;

                        if (isset($claim->merchantOffer)) {
                            $redemption_end_date = $claim->created_at->addDays($claim->merchantOffer->expiry_days)->endOfDay();
                        } else {
                            $redemption_end_date = $claim->created_at->endOfDay();// default to one day expired since offer expiry_days is not set
                        }
                    }

                    try {
                        $encrypted_data = $this->processEncrypt([
                            'offer_id' => $claim->merchantOffer->id,
                            'claim_id' => $claim->id,
                            'phone_no' => $transaction->user->phone_no
                        ]);

                        $merchantOffer = MerchantOffer::where('id', $transaction->transactionable_id)->first();

                        $this->sendPurchasedOfferNotification(
                            $transaction,
                            $merchantOffer,
                            $redemption_start_date,
                            $redemption_end_date,
                            $encrypted_data,
                            $claim
                        );
                    } catch (Exception $e) {
                        Log::error('Error sending PurchasedOfferNotification: ' . $e->getMessage());
                    }

                    if ($transaction->user->phone_no) {
                        try {
                            $this->smsService->sendSms($transaction->user->full_phone_no, config('app.name') . " - Voucher purchase successful. Redemption steps are sent via email.");
                        } catch (\Exception $e) {
                            Log::error('Error sending PurchasedOfferNotification SMS: ' . $e->getMessage());
                        }
                    }

                } else if ($transaction->transactionable_type == Product::class) {
                    $this->updateProductTransaction($request, $transaction);

                    if ($transaction->user->email) {
                        try {
                            $product = Product::where('id', $transaction->transactionable_id)->first();
                            $quantity = $transaction->amount / $product->unit_price;
                            $transaction->user->notify(new PurchasedGiftCardNotification($transaction->transaction_no, $transaction->updated_at, $product->name, $quantity, $transaction->amount));
                        } catch (\Exception $e) {
                            Log::error('Error sending PurchasedGiftCardNotification: ' . $e->getMessage());
                        }
                    }
                }

                Log::info('Payment return/callback success', [
                    'transaction_id' => $transaction->id,
                    'request' => request()->all()
                ]);

                // if transactionable_type is merchant_offer, get the relevant claim id
                $offer_name = null;
                $claim_id = null;
                $redemption_start_date = null;
                $redemption_end_date = null;
                if ($transaction->transactionable_type == \App\Models\MerchantOffer::class) {
                    $merchantOffer = MerchantOffer::where('id', $transaction->transactionable_id)->first();
                    $offer_name = $merchantOffer ? $merchantOffer->name : null;
                    $offer_id = $merchantOffer ? $merchantOffer->id : null;

                    $claim = MerchantOfferClaim::where('merchant_offer_id', $transaction->transactionable_id)
                        ->where('user_id', $transaction->user_id)
                        ->latest()
                        ->first();

                    if ($claim) {
                        $claim_id = $claim->id;
                        // redemption dates is claim created_at + offer expiry_days
                        $redemption_start_date = $claim->created_at;

                        if (isset($claim->merchantOffer)) {
                            $redemption_end_date = $claim->created_at->addDays($claim->merchantOffer->expiry_days)->endOfDay();
                        } else {
                            $redemption_end_date = $claim->created_at->endOfDay();// default to one day expired since offer expiry_days is not set
                        }
                    }
                }

                // return with js
                // window.flutter_inappwebview.callHandler('passData', {'someKey': 'someValue'});
                $params = [
                    'message' => 'Transaction Success',
                    'transaction_id' => $transaction->id,
                    'transaction_no' => $transaction->transaction_no,
                    'offer_name' => $offer_name,
                    'offer_id' => $offer_id,
                    'offer_claim_id' => $claim_id,
                    'redemption_start_date' => $redemption_start_date ? $redemption_start_date->toISOString() : null,
                    'redemption_end_date' => $redemption_end_date ? $redemption_end_date->toISOString() : null,
                    'success' => true
                ];

                if ($transaction->channel === 'app') {
                    return view('payment-return', $params);
                } else if ($transaction->channel === 'funhub_web') {
                    $hash_string = hash_hmac('sha256', $transaction->transaction_no, config('app.funhub_web_hash_secret'));
                    return redirect(config('app.funhub_web_link') . '?data=' . urlencode(json_encode($params)) . '&hash=' . $hash_string);
                }

                // ===================== PENDING
            } else if ($request->responseCode == 'PE') { // pending
                Log::info('Payment return/callback pending', [
                    'transaction_id' => $transaction->id,
                    'transaction_no' => $transaction->transaction_no,
                    'request' => request()->all()
                ]);
                if ($transaction->channel === 'app') {
                    return 'Transaction Still Pending';
                } else if ($transaction->channel === 'funhub_web') {
                    $hash_string = hash_hmac('sha256', $transaction->transaction_no, config('app.funhub_web_hash_secret'));
                    return redirect(config('app.funhub_web_link') . '?data=' . urlencode(json_encode(['message' => 'Transaction Still Pending', 'success' => false])) . '&hash=' . $hash_string);
                }
                // ===================== FAILED
            } else { // failed
                Log::error('Payment return failed', [
                    'error' => 'Transaction Failed - Gateway Response Code Failed',
                    'request' => request()->all()
                ]);
                $gatewayId = $request->mpay_ref_no;
                if (!$request->mpay_ref_no) {
                    // dont have mpay ref no when failed then use responseCode
                    $gatewayId = 'RES' . $request->responseCode;
                }
                $transaction->update([
                    'status' => \App\Models\Transaction::STATUS_FAILED,
                    'gateway_transaction_id' => $gatewayId,
				]);

                if ($transaction->transactionable_type == MerchantOffer::class) {
                    $this->updateMerchantOfferTransaction($request, $transaction);
                } else if ($transaction->transactionable_type == Product::class) {
                    $this->updateProductTransaction($request, $transaction);
                }

                $params = [
                    'message' => 'Transaction Failed - Gateway Response Code Failed [2]',
                    'transaction_id' => $transaction->id,
                    'transaction_no' => $transaction->transaction_no,
                    'success' => false
                ];
                if ($transaction->channel === 'app') {
                    return view('payment-return', $params);
                } else if ($transaction->channel === 'funhub_web') {
                    $hash_string = hash_hmac('sha256', $transaction->transaction_no, config('app.funhub_web_hash_secret'));
                    return redirect(config('app.funhub_web_link') . '?data=' . urlencode(json_encode($params)) . '&hash=' . $hash_string);
                }
            }
        } else {
            Log::error('Payment return failed', [
                'error' => 'Transaction not found',
                'request' => request()->all(),
                'invno' => request()->invno ?? null
            ]);

            $params = [
                'message' => 'Transaction Failed - No transaction',
                'transaction_id' => null,
                'success' => false
            ];

            // Check if channel parameter exists in request, default to 'app' if not specified
            $channel = request()->channel ?? 'app';

            if ($channel === 'app') {
                return view('payment-return', $params);
            } else if ($channel === 'funhub_web') {
                return redirect(config('app.funhub_web_link') . '?data=' . urlencode(json_encode($params)));
            }

            // Default fallback to app view if channel is neither app nor funhub_web
            return view('payment-return', $params);
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

            Log::info('[PaymentController] Updated Merchant Offer Claim to Success', [
                'transaction_id' => $transaction->id,
                'merchant_offer_id' => $transaction->transactionable_id,
            ]);

            try {
                $locale = $transaction->user->last_lang ?? config('app.locale');

                if ($transaction->channel == 'funhub_web' && $transaction->email) {
                    // Send email notification to transaction email for web transactions
                    Notification::route('mail', $transaction->email)
                        ->notify((new OfferClaimed($merchantOffer, $transaction->user, 'fiat', $transaction->amount))->locale($locale));
                } else {
                    // Send notification to user for app transactions
                    $transaction->user->notify((new OfferClaimed($merchantOffer, $transaction->user, 'fiat', $transaction->amount))->locale($locale));
                }
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
                    // release voucher
                    if ($claim->voucher_id) {
                        // First check if this voucher is successfully claimed by anyone 
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
                            }
                        } else {
                            Log::info('Voucher has successful claim, skipping release', [
                                'transaction_id' => $transaction->id,
                                'voucher_id' => $claim->voucher_id,
                                'successful_claim_id' => $successfulClaim->id
                            ]);
                        }
                    }

                    Log::info('Updated Merchant Offer Claim to Failed, released voucher', [
                        'transaction_id' => $transaction->id,
                        'merchant_offer_id' => $transaction->transactionable_id,
                    ]);
                } catch (Exception $ex) {
                    Log::error('Updated Merchant Offer Claim to Failed, Release voucher failed', [
                        'transaction_id' => $transaction->id,
                        'merchant_offer_id' => $transaction->transactionable_id,
                        'claim' => json_encode($claim),
                        'error' => $ex->getMessage()
                    ]);
                }

                // revert stock
                try {
                    $latestQuantity = MerchantOfferVoucher::where('merchant_offer_id', $merchantOffer->id)
                        ->whereNull('owned_by_id')
                        ->where('voided', false)
                        ->count();

                    $merchantOffer->quantity = $latestQuantity;
                    $merchantOffer->save();

                    Log::info('Updated Merchant Offer Claim to Failed, updated merchant offer stock count', [
                        'transaction_id' => $transaction->id,
                        'merchant_offer_id' => $transaction->transactionable_id,
                    ]);
                } catch (Exception $ex) {
                    Log::error('Updated Merchant Offer Claim to Failed, Stock Quantity Revert Failed', [
                        'transaction_id' => $transaction->id,
                        'merchant_offer_id' => $transaction->transactionable_id,
                        'claim' => json_encode($claim),
                        'error' => $ex->getMessage()
                    ]);
                }
            }
        }
    }

    /**
     * Card Tokenization Return form Gateway (called by Gateway)
     *
     * @param Request $request
     * @return View
     */
    public function cardTokenizationReturn(Request $request)
    {
        // validate secureHash
        Log::info('[cardTokenizationReturn] Card Tokenization Return from MPAY', [
            'request' => $request->all(),
        ]);

        if (($request->responseCode == 0 || $request->responseCode == '0') && $request->has('token')) {
            // success
            // get transaction from uuid
            $transaction = Transaction::where('transaction_no', $request->invno)->first();
            if (!$transaction) {
                Log::error('Mpay Card Tokenization Failed: Transaction not found', [
                    'uuid' => $request->uuid,
                    'request' => $request->all(),
                ]);

                return view('payment-return', [
                    'message' => 'Transaction Failed - No transaction',
                    'transaction_id' => null,
                    'success' => false
                ]);
            } else {
                $transaction->update([
                    'status' => Transaction::STATUS_SUCCESS,
                    'gateway_transaction_id' => ($request->has('mpay_ref_no')) ? $request->mpay_ref_no : 'NO REF NO',
                ]);

                $user = User::where('id', $transaction->user_id)->first();

                $cardType = null;
                if ($request->paymentType == 'Master' || $request->paymentType == 'MasterCard' || $request->paymentType == 'MASTER') {
                    $cardType = 'master';
                } elseif ($request->paymentType == 'Visa' || $request->paymentType == 'VisaCard' || $request->paymentType == 'VISA') {
                    $cardType = 'visa';
                } else if ($request->paymentType == 'Amex' || $request->paymentType == 'AmexCard') {
                    $cardType = 'amex';
                }

                if ($user) {
                    // check if card exists in user_cards table
                    $cardExists = UserCard::where('user_id', $user->id)
                        ->where('card_type', $cardType)
                        ->where('card_last_four', substr($request->maskedPAN, -4))
                        ->exists();

                    if (!$cardExists) {
                        // create new card
                        $user->cards()->create([
                            'card_type' => $cardType,
                            'card_last_four' => substr($request->maskedPAN, -4), // last four digits of card number
                            'card_holder_name' => '',
                            'card_expiry_month' => '',
                            'card_expiry_year' => '',
                            'card_token' => $request->token,
                            'is_default' => $user->cards()->count() == 0,
                        ]);
                    } else {
                        // update token
                        UserCard::where('user_id', $user->id)
                            ->where('card_type', $cardType)
                            ->where('card_last_four', substr($request->maskedPAN, -4))
                            ->update([
                                'card_token' => $request->token,
                            ]);
                    }

                    Log::info('Mpay Card Tokenization Success', [
                        'uuid' => $request->invno,
                        'mpay_returrned' => $request->all(),
                        'user' => $user->id,
                    ]);

                    return view('payment-return', [
                        'message' => 'Card Added',
                        'transaction_id' => $transaction->id,
                        'success' => true
                    ]);
                } else {
                    Log::error('Mpay Card Tokenization Failed: User not found', [
                        'uuid' => $request->uuid,
                        'request' => $request->all(),
                    ]);

                    return view('payment-return', [
                        'message' => 'Transaction Failed - User not found',
                        'transaction_id' => null,
                        'success' => false
                    ]);
                }
            }
        } else if (
            ($request->responseCode == 0 || $request->responseCode == '0')
            && !$request->has('token')
        ) {
            // ===================== CARD TOKENIZATION UPDATE, SO RE-QUERY CARD TOKLEN
            $transaction = Transaction::where('transaction_no', $request->invno)->first();
            if (!$transaction) {
                Log::error('[PaymentController] Mpay Card Tokenization Failed: Transaction not found', [
                    'request' => $request->all(),
                ]);

                return view('payment-return', [
                    'message' => 'Transaction Failed - Transaction not found',
                    'transaction_id' => null,
                    'success' => false
                ]);
            }

            $user = User::find($transaction->user_id);
            // token not returned meaning card was tokenized before, start querying first for the token
            $results = $this->gateway->queryCardToken($transaction->user_id, $request->invno);

            if ($results['responseCode'] == '0') {
                // success, update token of card based on cardLast4Digit
                $user->cards()->where('card_last_four', $results['cardLast4Digit'])->update([
                    'card_token' => $results['token'],
                ]);
                Log::info('Mpay Card Tokenization Success (Updated)', [
                    'uuid' => $request->uuid,
                    'mpay_returrned' => $request->all(),
                    'user' => $user->id,
                ]);

                return view('payment-return', [
                    'message' => 'Card Added',
                    'transaction_id' => $transaction->id,
                    'success' => true
                ]);
            } else {
                return view('payment-return', [
                    'message' => 'Transaction Failed - Card token query failed',
                    'transaction_id' => null,
                    'success' => false
                ]);
            }
        } else {
            // failed
            return view('payment-return', [
                'message' => 'Transaction Failed - Unknown Failure',
                'transaction_id' => null,
                'success' => false
            ]);
        }
    }

    /**
     * Get available payment types
     *
     * @group Payment
     * @response status=200 {
     *  "availablePaymentTypes": [
     *      "fpx",
     *      "card"
     *  ],
     *  "server_time": "2023-10-01 12:00:00",
     *  "last_payment_method": "FPX-CIMB"
     * }
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getAvailablePaymentTypes()
    {
        $user = auth()->user();

        // user last users payment type , get last successful transaction
        $lastSuccessfulTransaction = Transaction::where('user_id', $user->id)
            ->where('status', Transaction::STATUS_SUCCESS)
            ->orderBy('id', 'desc')
            ->first();

        $lastSuccessfulTransactionType = null;
        if ($lastSuccessfulTransaction) {
            $lastSuccessfulTransactionType = $lastSuccessfulTransaction->payment_method;
        }

        $availablePaymentTypes = $this->gateway->checkAvailablePaymentTypes();
        return response()->json([
            'availablePaymentTypes' => $availablePaymentTypes,
            'server_time' => Carbon::now(),
            'last_payment_method' => $lastSuccessfulTransactionType
        ]);
    }

    /**
     * Get Funbox Ringgit Value
     *
     * @return JsonResponse
     * @group Payment
     * @response status=200 {
     *  "funbox_ringgit_value": 5
     * }
     */
    public function getFunboxRinggitValue()
    {
        return response()->json([
            'funbox_ringgit_value' => config('app.funbox_ringgit_value')
        ]);
    }

    /**
     * Send purchased offer notification based on transaction channel
     */
    private function sendPurchasedOfferNotification($transaction, $merchantOffer, $redemption_start_date, $redemption_end_date, $encrypted_data, $claim)
    {
        Log::info('[PaymentController] Send Purchased Offer Notification', [
            'transaction' => $transaction,
            'merchantOffer' => $merchantOffer,
            'redemption_start_date' => $redemption_start_date,
            'redemption_end_date' => $redemption_end_date,
            'encrypted_data' => $encrypted_data,
            'claim' => $claim
        ]);

        $merchantOfferCover = $merchantOffer->getFirstMediaUrl(MerchantOffer::MEDIA_COLLECTION_NAME);

        $notification = new PurchasedOfferNotification(
            $transaction->transaction_no,
            $transaction->updated_at,
            $merchantOffer->name,
            1,
            $transaction->amount,
            'MYR',
            $transaction->created_at->format('Y-m-d'),
            $transaction->created_at->format('H:i:s'),
            $redemption_start_date ? $redemption_start_date->format('j/n/Y') : null,
            $redemption_end_date ? $redemption_end_date->format('j/n/Y') : null,
            $encrypted_data,
            $claim->merchantOffer->user->merchant->brand_name,
            $transaction->name,
            $merchantOfferCover
        );

        if ($transaction->channel == 'funhub_web' && $transaction->email) {
            // send email notification to transaction email
            Notification::route('mail', $transaction->email)->notify(notification: $notification);
        } else {
            // send notification to user for app transactions
            $transaction->user->notify($notification);
        }
    }

    public function processEncrypt($data)
    {

        try {
            // we use the same key and IV
            $key = hex2bin($this->checkout_secret);
            $iv = hex2bin($this->checkout_secret);

            // we receive the encrypted string from the post
            // finally we trim to get our original string
            $encrypted_data = openssl_encrypt(json_encode($data), 'AES-128-CBC', $key, 0, $iv);

            if ($encrypted_data === false) {
                Log::error('Error encrypting data', [
                    'error' => 'Encryption Failed - ' . openssl_error_string(),
                    'data' => json_encode($data),
                ]);
            }

            return $encrypted_data;

        } catch (\Exception $e) {
            Log::error('Error encrypting data', [
                'error' => $e->getMessage(),
                'data' => $data
            ]);

            return '';
        }
    }
}
