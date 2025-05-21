<?php

namespace App\Http\Controllers\Api;

use App\Events\MerchantOfferClaimed;
use App\Http\Controllers\Controller;
use App\Http\Resources\MerchantOfferClaimResource;
use App\Http\Resources\MerchantOfferResource;
use App\Http\Resources\ProductResource;
use App\Http\Resources\ProductHistoryResource;
use App\Models\Interaction;
use App\Models\Merchant;
use App\Models\MerchantOffer;
use App\Models\MerchantOfferClaim;
use App\Models\MerchantOfferVoucher;
use App\Models\Product;
use App\Models\Transaction;
use App\Models\UserCard;
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
     * Get Products for Sale (Gift Cards)
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     *
     * @group Product
     * @response scenario=success {
     * "current_page": 1,
     * "data": []
     * }
     */
    public function index(Request $request)
    {
        $products = Product::with('rewards')
            ->published()
            ->normal()
			->orderBy('order')
            ->get();

        return ProductResource::collection($products);
    }

    /**
     * Get Products for Sale (Limited Gift Cards)
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     *
     * @group Product
     * @response scenario=success {
     * "current_page": 1,
     * "data": []
     * }
     */
    public function limited(Request $request)
    {
        $products = Product::with('rewards')
            ->published()
            ->limited()
            ->orderBy('order')
            ->get();

        return ProductResource::collection($products);
    }

    /**
     * Get Product By ID
     *
     * @param Product $product
     * @return \Illuminate\Http\JsonResponse
     *
     * @group Product
     * @queryParam product_id integer required Product ID. Example: 1
     * @response scenario=success {
     * "data": {}
     * }
     *
     */
    public function show($id)
    {
        $product = Product::where('id', $id)
            ->published()
            ->first();

        if (!$product) {
            return response()->json(['message' => 'Product not found'], 422);
        }

        return new ProductResource($product);
    }

    /**
     * Get Total Quantity of Funcard purchased by user
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     *
     * @group Product
     * @queryParam from_date string optional Filter by transaction created from this date (Y-m-d format). Example: 2025-01-01
     * @queryParam to_date string optional Filter by transaction created until this date (Y-m-d format). Example: 2025-01-16
     * @queryParam product_ids integer optional Filter by product_id. Example: 1,2,3,4 or 1
     * @response scenario=success {
     *     "quantity": 10
     * }
     */

    public function getTotalPurchasedByUser(Request $request) {
        // base query builder for date filtering
        $dateQuery = function ($query) use ($request) {
            if ($request->has('from_date')) {
                $query->whereDate('created_at', '>=', $request->from_date);
            }
            if ($request->has('to_date')) {
                $query->whereDate('created_at', '<=', $request->to_date);
            }
            return $query;
        };

        // filter by status if provided, default to success product_id 1
        $product_ids = $request->has('product_ids') ? explode(',', $request->get('product_ids')) : [1];

        $transaction = Transaction::where('user_id', auth()->user()->id)
            ->where('status', Transaction::STATUS_SUCCESS)
            ->where('transactionable_type', Product::class)
            ->whereIn('transactionable_id', $product_ids)
            ->when($request->has(['from_date', 'to_date']), function ($query) use ($dateQuery) {
                return $dateQuery($query);
            })
            ->count();

        return response()->json([
            'quantity' => (int) $transaction
        ]);
    }

    /**
     * Get Funcard or Funbox for last 30 days Transactions History
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     *
     * @group Product
     * @response scenario=success {
     * "data": []
     * }
     */

    public function getHistory(Request $request) {

        $query = Transaction::where('transactions.user_id', auth()->user()->id)
            ->where('transactions.status', Transaction::STATUS_SUCCESS)
            ->where('transactions.transactionable_type', Product::class)
            ->whereDate('transactions.created_at', '>=', Carbon::now()->subDays(30)) // Last 30 days
            ->whereDate('transactions.created_at', '<=', Carbon::now())
            ->join('point_ledgers', function ($join) {
                $join->on('transactions.user_id', '=', 'point_ledgers.user_id')
                     ->on('transactions.transaction_no', '=', 'point_ledgers.remarks');
            })
            ->select('transactions.*', 'point_ledgers.amount AS point_amount') 
            ->orderBy('transactions.created_at', 'desc')
            ->get();


        return ProductHistoryResource::collection($query);
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
     * @bodyParam card_id integer required_if:fiat_payment_method,card Card ID. Example: 1
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
            'card_id' => 'exists:user_cards,id',
            'quantity' => 'required|integer|min:1',
			'referral_code' => 'nullable|string',
            'promotion_code' => 'nullable|string|exists:promotion_codes,code'
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
        
        // Handle promotion code if provided
        $promotionDiscount = 0;
        $appliedPromotionCode = null;
        
        if ($request->has('promotion_code')) {
            $promotionCode = \App\Models\PromotionCode::where('code', $request->promotion_code)
//                ->where('is_redeemed', false)
                ->where('status', true)
                ->first();
            
            if ($promotionCode && $promotionCode->isActive()) {
                $promotionCodeGroup = $promotionCode->promotionCodeGroup;

                // Check if this is a fixed amount discount promotion code
                if ($promotionCodeGroup && $promotionCodeGroup->discount_type == 'fix_amount' &&
                    $promotionCodeGroup->discount_amount > 0) {

                    // Check user usage limits
                    $user = request()->user();
                    $userPromoCode = $promotionCode->users()->where('user_id', $user->id)->first();
                    $userUsageCount = $userPromoCode ? $userPromoCode->pivot->usage_count : 0;

                    // Check if user has reached their limit
                    if ($promotionCodeGroup->per_user_limit_count > 0 &&
                        $userUsageCount >= $promotionCodeGroup->per_user_limit_count) {
                        return response()->json([
                            'message' => __('messages.success.promotion_code_controller.Redemption_limit_reached')
                        ], 400);
                    }

                    // Check if the code has reached its global unique user limit
                    if ($promotionCode->code_quantity &&
                        $promotionCode->used_code_count >= $promotionCode->code_quantity &&
                        !$userPromoCode) {
                        return response()->json([
                            'message' => __('messages.success.promotion_code_controller.Redemption_limit_reached')
                        ], 400);
                    }

                    $promotionDiscount = $promotionCodeGroup->discount_amount;
                    $appliedPromotionCode = $promotionCode;

                    // Apply the discount
                    $net_amount = $net_amount - $promotionDiscount;
                    
                    Log::info('[ProductController] Promotion code discount applied', [
                        'promotion_code' => $request->promotion_code,
                        'discount_amount' => $promotionDiscount,
                        'original_amount' => (($product->discount_price) ?? $product->unit_price) * $request->quantity,
                        'final_amount' => $net_amount,
                        'user_usage_count' => $userUsageCount
                    ]);
                }
            } else {
                return response()->json([
                    'message' => __('messages.success.promotion_code_controller.Code_invalid')
                ], 400);
            }
        }

        $walletType = null;
        // if request has payment type, then use it
        if ($request->has('wallet_type')) {
            $walletType = $request->wallet_type;
        }

        // get card if payment mode via card
        $selectedCard = null;
        if ($request->fiat_payment_method == 'card' && $request->has('card_id')) {
            // check user has a saved card
            $selectedCard = UserCard::where('id', $request->card_id)
                ->where('user_id', $user->id)
                // ->where('is_default', true)
                // ->notExpired()
                ->first();
        }

        // create payment transaction, pending status
        $transaction = $this->transactionService->create(
            $product,
            $net_amount,
            config('app.default_payment_gateway'),
            $user->id,
            ($walletType) ? $walletType : $request->fiat_payment_method,
            'app',
            ($request->has('email') ? $request->email : null),
            ($request->has('name') ? $request->name : null),
            ($request->has('referral_code') ? $request->referral_code : null),
        );

        // Associate promotion code with transaction if provided
        if ($appliedPromotionCode) {
            $transaction->promotionCodes()->attach($appliedPromotionCode->id);

            Log::info('[ProductController] Promotion code associated with transaction', [
                'promotion_code_id' => $appliedPromotionCode->id,
                'transaction_id' => $transaction->id,
                'user_id' => $user->id,
                'discount_amount' => $promotionDiscount
            ]);
        }

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
                $user->email ?? null,
                ($walletType) ? $walletType : null, // FPX-CIMB,GRAB,TNG
                $selectedCard ? $selectedCard->card_token : null,
                $user->id
            );

            //if this product has limited supply, reduce quantity
            if ($product->unlimited_supply == 0) {
                $product->quantity = $product->quantity - $request->quantity;
                $product->save();
            }

            return response()->json([
                'message' => __('messages.success.product_controller.Redirect_to_Gateway'),
                'transaction_no' => $transaction->transaction_no,
                'gateway_data' => $mpayData,
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
