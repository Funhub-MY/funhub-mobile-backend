<?php

namespace App\Http\Controllers\Api;

use App\Events\MerchantOfferClaimed;
use App\Http\Controllers\Controller;
use App\Http\Resources\MerchantOfferClaimResource;
use App\Http\Resources\MerchantOfferResource;
use App\Models\Interaction;
use App\Models\Merchant;
use App\Models\MerchantOffer;
use App\Models\MerchantOfferClaim;
use App\Models\MerchantOfferVoucher;
use App\Models\Product;
use App\Models\Transaction;
use App\Notifications\OfferClaimed;
use App\Notifications\OfferRedeemed;
use App\Services\Mpay;
use App\Services\PointService;
use App\Services\TransactionService;
use App\Traits\QueryBuilderTrait;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class ProductController extends Controller
{
    use QueryBuilderTrait;

    protected $pointService, $transactionService;

    public function __construct()
    {
        $this->pointService = new PointService();
        $this->transactionService = new TransactionService();
    }

    /**
     * Post Checkout
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     *
     * @group Product
     * @subgroup Product Rewards
     * @bodyParam product_id integer required Product ID. Example: 1
     * @bodyParam quantity integer required Quantity. Example: 1
     * @bodyParam payment_method string required Payment Method. Example: fiat
     * @bodyParam fiat_payment_method string required_if:payment_method,fiat Payment Method. Example: fpx/card
     * @bodyParam wallet_type string optional Wallet Type. Example: TNG/FPX-CIMB
     * @response scenario=success {
     * "message": "Redirect to Gateway"
     * }
     * @response scenario=insufficient_product_quantity {
     * "message": "Product is sold out"
     * }
     * @response scenario=fiat_payment_mode {
     * "message": "Redirect to gateway",
     * "gateway_data": [
     *      'url' => $this->url .'/payment/eCommerce',
     *       'secureHash' => $this->generateHashForRequest($this->mid, $invoice_no, $amount),
     *       'mid' => $this->mid,
     *       'invno' => $invoice_no,
     *       'capture_amt' => $amount,
     *       'desc' => $desc,
     *       'postURL' => $redirectUrl,
     *       'phone' => $phoneNo,
     *       'email' => $email,
     *       'param' => $param,
     *       'authorize' => 'authorize'
     *   ];
     * }
     */
    public function postCheckout(Request $request)
    {
        $request->validate([
            'product_id' => 'required|integer',
            'payment_method' => 'required',
            'fiat_payment_method' => 'required_if:payment_method,fiat,in:fpx,card',
            'quantity' => 'required|integer|min:1'
        ]);

       // check if user has verified email address
        if (!auth()->user()->hasVerifiedEmail()) {
            return response()->json([
                'message' => __('messages.error.product_controller.Please_verify_your_email_address_first')
            ], 422);
        }

        $product = Product::where('id', request()->product_id)
            ->published()
            //->with('rewards')
            ->first();

        if (!$product) {
            return response()->json([
                'message' => __('messages.error.product_controller.Product_is_no_longer_valid')
            ], 422);
        }

        if ($product->unlimited_supply == 0) { //this product has limited supply
            if ($product->quantity < $request->quantity) {
                return response()->json([
                    'message' => __('messages.error.product_controller.Product_is_sold_out')
                ], 422);
            }
        }

        //proceed to transaction
        $user = request()->user();
        $net_amount = (($product->discount_price) ?? $product->unit_price)  * $request->quantity;

        // if request has payment type, then use it
        if ($request->has('wallet_type')) {
            $walletType = $request->wallet_type;
        }

        // create payment transaction, pending status
        $transaction = $this->transactionService->create(
            $product,
            $net_amount,
            config('app.default_payment_gateway'),
            $user->id,
            $request->fiat_payment_method,
        );

        // if gateway is mpay call mpay service generate Hash for frontend form
        if ($transaction->gateway == 'mpay') {

            $mpayService = new \App\Services\Mpay(
                config('services.mpay.mid'),
                config('services.mpay.hash_key'),
                ($request->fiat_payment_method) ? $request->fiat_payment_method : false,
            );

            // generates required form post fields data for frontend(app) usages
            $mpayData = $mpayService->createTransaction(
                $transaction->transaction_no,
                $net_amount,
                $transaction->transaction_no,
                secure_url('/payment/return'),
                $user->full_phone_no ?? null,
                $user->email ?? null
                ($walletType) ? $walletType : null,
            );

            //if this product has limited supply, reduce quantity
            if ($product->unlimited_supply == 0) {
                $product->quantity = $product->quantity - $request->quantity;
                $product->save();
            }

            return response()->json([
                'message' => __('messages.success.product_controller.Redirect_to_Gateway'),
                'transaction_no' => $transaction->transaction_no,
                'gateway_data' => $mpayData
            ], 200);
        }
    }

    /**
     * Cancel Product Checkout
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     *
     * @group Product
     * @bodyParam transaction_no string required Transaction No. Example: 11234455
     *
     * @response scenario=success {
     * "message": "Transaction cancelled"
     * }
     */
    public function postCancelCheckout(Request $request)
    {
        $this->validate($request, [
            'transaction_no' => 'required'
        ]);

        // find transaciton is by user and transaction_no
        $transaction = Transaction::where('user_id', auth()->user()->id)
            ->where('transaction_no', $request->transaction_no)
            ->first();

        if (!$transaction) {
            return response()->json([
                'message' => __('messages.error.product_controller.Transaction_not_found')
            ], 404);
        }

        Log::info('[ProductController] User cancelled transaction, but status maintain PENDING', ['transaction' => $transaction]);

        // update transaction status to FAILED
        // dont update to failed directly
        // $transaction = $this->transactionService->updateTransactionStatus($transaction->id, Transaction::STATUS_FAILED);

        return response()->json([
            'message' => __('messages.success.product_controller.Transaction_cancelled')
        ], 200);
    }

}
