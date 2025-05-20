<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PromotionCode;
use App\Models\PromotionCodeGroup;
use App\Models\User;
use App\Services\PointService;
use App\Services\PointComponentService;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class PromotionCodeController extends Controller
{
    public function __construct(
        protected PointService $pointService,
        protected PointComponentService $pointComponentService
    ) {}

    /**
     * Redeem a promotion code
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     * 
     * @group Promotion Code
     * @bodyParam code string required The code to redeem. Example: ABCD123ABCDD
     * @response scenario=success {
     * "message": "Code redeemed successfully",
     * "rewards": [
     *   {
     *     "id": 1,
     *     "name": "Funhub",
     *     "description": "Funhub",
     *     "components": [],
     *   }
     * ],
     * "reward_components": [
     *   {
     *     "id": 1,
     *     "name": "Funhub",
     *     "description": "Funhub",
     *     "components": [],
     *   }
     * ]
     * }
     */
    public function redeem(Request $request)
    {
        $request->validate([
            'code' => 'required|string',
        ]);
        try {
            return DB::transaction(function () use ($request) {
                $code = $request->input('code');

                // Use case-sensitive comparison for the code
                $promotionCode = PromotionCode::whereRaw('BINARY code = ?', [$code])
                    ->first();
                
                // Initialize default response data
                $rewards = [];
                $rewardComponents = [];
                $responseData = [
                    'success' => false,
                    'promotion_code_group' => null,
                    'rewards' => $rewards,
                    'reward_components' => $rewardComponents
                ];
                
                if (!$promotionCode) {
                    Log::info('[PromotionCodeController] Invalid promotion code', [
                        'code' => $code
                    ]);
                    
                    return response()->json(array_merge($responseData, [
                        'message' => __('messages.success.promotion_code_controller.Invalid_code'),
                    ]), 404);
                }
                
                // Update response data with promotion code information
                $responseData['promotion_code'] = $promotionCode->only(['id', 'code']);
                $responseData['promotion_code_group'] = $promotionCode->promotionCodeGroup ? $promotionCode->promotionCodeGroup->only(['id', 'name', 'description']) : null;
                $responseData['rewards'] = $promotionCode->reward;
                $responseData['reward_components'] = $promotionCode->rewardComponent;
                
                Log::info('[PromotionCodeController] Found promotion code', [
                    'promotion_code_id' => $promotionCode->id,
                    'claimed_by_id' => $promotionCode->claimed_by_id
                ]);

                // check if it's already claimed
                if ($promotionCode->claimed_by_id) {
                    return response()->json(array_merge($responseData, [
                        'message' => __('messages.success.promotion_code_controller.Code_already_claimed'),
                    ]), 400);
                }

                // Check if this is a discount code (should only be used at checkout, not for redeeming)
                if ($promotionCode->promotionCodeGroup && $promotionCode->promotionCodeGroup->use_fix_amount_discount) {
                    return response()->json(array_merge($responseData, [
                        'message' => __('messages.success.promotion_code_controller.Code_only_can_use_when_checkout'),
                    ]), 400);
                }
                
                // check if promotion code group campaign is still active
                if ($promotionCode->promotionCodeGroup) {
                    if (!$promotionCode->promotionCodeGroup->status) {
                        return response()->json(array_merge($responseData, [
                            'message' => __('messages.success.promotion_code_controller.Campaign_disabled'),
                        ]), 400);
                    }

                    $now = now();
                    if ($promotionCode->promotionCodeGroup->campaign_until && $now->gt($promotionCode->promotionCodeGroup->campaign_until)) {
                        return response()->json(array_merge($responseData, [
                            'message' => __('messages.success.promotion_code_controller.Campaign_ended'),
                        ]), 400);
                    }

                    if ($promotionCode->promotionCodeGroup->campaign_from && $now->lt($promotionCode->promotionCodeGroup->campaign_from)) {
                        return response()->json(array_merge($responseData, [
                            'message' => __('messages.success.promotion_code_controller.Campaign_not_started'),
                        ]), 400);
                    }
                }

                // check if promotion code is active
                if (!$promotionCode->isActive()) {
                    return response()->json(array_merge($responseData, [
                        'message' => __('messages.success.promotion_code_controller.Code_not_active_or_expired'),
                    ]), 400);
                }

				// Check user eligibility based on user type
				if ($promotionCode->promotionCodeGroup->user_type !== array_key_first(PromotionCodeGroup::USER_TYPES)) { // 'all' is the first key
					$user = auth()->user();
					$userCreatedAt = Carbon::parse($user->created_at);
					Log::info($userCreatedAt);
					$isNewUser = $userCreatedAt->diffInHours($now) <= 48; // New user = registered within 48 hours

					// Check if user type is 'new' but user is not a new user
					if ($promotionCode->promotionCodeGroup->user_type === array_keys(PromotionCodeGroup::USER_TYPES)[1] && !$isNewUser) { // 'new' is the second key
						return response()->json(array_merge($responseData, [
							'message' => __('messages.success.promotion_code_controller.User_not_eligible'),
						]), 400);
					}

					// Check if user type is 'old' but user is a new user
					if ($promotionCode->promotionCodeGroup->user_type === array_keys(PromotionCodeGroup::USER_TYPES)[2] && $isNewUser) { // 'old' is the third key
						return response()->json(array_merge($responseData, [
							'message' => __('messages.success.promotion_code_controller.User_not_eligible'),
						]), 400);
					}
				}

                // mark as claimed
                $promotionCode->claimed_by_id = auth()->id();
                $promotionCode->is_redeemed = true;
                $promotionCode->redeemed_at = now();
                $promotionCode->save();

                Log::info('[PromotionCodeController] Marked promotion code as claimed', [
                    'promotion_code_id' => $promotionCode->id,
                    'user_id' => auth()->id()
                ]);

                // process rewards
                $rewards = $promotionCode->reward;
                $rewardComponents = $promotionCode->rewardComponent;
                foreach ($rewards as $reward) {
                    Log::info('[PromotionCodeController] Crediting reward', [
                        'reward_id' => $reward->id,
                        'quantity' => $reward->pivot->quantity
                    ]);

                    $this->pointService->credit(
                        $promotionCode,
                        auth()->user(),
                        $reward->pivot->quantity * $reward->points,
                        'Promotion code: ' . $code
                    );
                }

                foreach ($rewardComponents as $component) {
                    Log::info('[PromotionCodeController] Crediting reward component', [
                        'component_id' => $component->id,
                        'quantity' => $component->pivot->quantity
                    ]);

                    $this->pointComponentService->credit(
                        $promotionCode,
                        $component,
                        auth()->user(),
                        $component->pivot->quantity,
                        'Promotion code: ' . $code
                    );
                }

                // Update rewards and reward components with the actual data for success response
                $rewards = $promotionCode->reward;
                $rewardComponents = $promotionCode->rewardComponent;
                
                return response()->json(array_merge($responseData, [
                    'success' => true,
                    'message' => __('messages.success.promotion_code_controller.Code_redeemed_successfully'),
                    'rewards' => $rewards,
                    'reward_components' => $rewardComponents,
                ]));
            });
        } catch (Exception $e) {
            Log::error('[PromotionCodeController] Error redeeming promo code', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            
            // Default response for exceptions
            return response()->json([
                'success' => false,
                'message' => __('messages.success.promotion_code_controller.Invalid_code'),
                'promotion_code_group' => null,
                'rewards' => [],
                'reward_components' => []
            ], 404);
        }
    }


	/**
	 * Redeem a promotion code
	 *
	 * @param Request $request
	 * @return \Illuminate\Http\JsonResponse
	 *
	 * @group Promotion Code
	 * @bodyParam code string required The code to redeem. Example: ABCD123ABCDD
	 * @response scenario=success {
	 * "message": "Code redeemed successfully",
	 * "rewards": [
	 *   {
	 *     "id": 1,
	 *     "name": "Funhub",
	 *     "description": "Funhub",
	 *     "components": [],
	 *   }
	 * ],
	 * "reward_components": [
	 *   {
	 *     "id": 1,
	 *     "name": "Funhub",
	 *     "description": "Funhub",
	 *     "components": [],
	 *   }
	 * ]
	 * }
	 */
	/**
	 * Check if a promotion code is valid for checkout
	 *
	 * @param Request $request
	 * @return \Illuminate\Http\JsonResponse
	 *
	 * @group Promotion Code
	 * @bodyParam code string required The code to check. Example: ABCD123ABCDD
	 * @bodyParam product_ids array optional The product IDs to check against. Example: [1, 2, 3]
	 */
	public function postCheckPromoCode(Request $request)
	{
		$request->validate([
			'product_id' => 'required|integer',
			'code' => 'required|string',
			'payment_method' => 'required|string',
		]);

		try {
			$code = $request->input('code');
			$productId = $request->input('product_id');
			$paymentMethod = $request->input('payment_method');

			// Find the promotion code (case-sensitive)
			$promotionCode = PromotionCode::whereRaw('BINARY code = ?', [$code])->first();

			if (!$promotionCode) {
				return response()->json([
					'success' => false,
					'message' => __('messages.success.promotion_code_controller.Code_invalid'),
				], 404);
			}

			// Get the promotion code group
			$codeGroup = $promotionCode->promotionCodeGroup;

			// Prepare basic response data that will be included in all responses
			$responseData = [
				'promotion_code' => $promotionCode->only(['id', 'code']),
				'promotion_code_group' => $codeGroup->only(['id', 'name', 'description', 'use_fix_amount_discount', 'discount_amount']),
			];

			// Prepare discount data
			$discountData = [];
			if ($codeGroup->use_fix_amount_discount) {
				$discountData = [
					'type' => 'fixed',
					'amount' => $codeGroup->discount_amount,
				];
			} else {
				// For reward-based promo codes, we'll return the rewards
				$rewards = $promotionCode->reward;
				$rewardComponents = $promotionCode->rewardComponent;

				$discountData = [
					'type' => 'reward',
					'rewards' => $rewards,
					'reward_components' => $rewardComponents,
				];
			}
			$responseData['discount'] = $discountData;

			// Check if the code has been redeemed
			if ($promotionCode->claimed_by_id) {
				return response()->json(array_merge([
					'success' => false,
					'message' => __('messages.success.promotion_code_controller.Code_already_used'),
				], $responseData), 400);
			}

			// Check if the promotion code group is active
			if (!$codeGroup->status) {
				return response()->json(array_merge([
					'success' => false,
					'message' => __('messages.success.promotion_code_controller.Campaign_disabled'),
				], $responseData), 400);
			}

			// Check expired date (campaign_until)
			$now = now();
			if ($codeGroup->campaign_until && $now->gt($codeGroup->campaign_until)) {
				return response()->json(array_merge([
					'success' => false,
					'message' => __('messages.success.promotion_code_controller.Code_expired'),
				], $responseData), 400);
			}

			// Check if this is a reward code (should only be used for redeeming, not at checkout)
			if (!$codeGroup->use_fix_amount_discount) {
				return response()->json(array_merge([
					'success' => false,
					'message' => __('messages.success.promotion_code_controller.Code_only_can_use_when_redeem_reward'),
				], $responseData), 400);
			}

			// Check if campaign has started
			if ($codeGroup->campaign_from && $now->lt($codeGroup->campaign_from)) {
				return response()->json(array_merge([
					'success' => false,
					'message' => __('messages.success.promotion_code_controller.Campaign_not_started'),
				], $responseData), 400);
			}

			// Check user eligibility based on user type
			if ($codeGroup->user_type !== array_key_first(PromotionCodeGroup::USER_TYPES)) { // 'all' is the first key
				$user = auth()->user();
				$userCreatedAt = Carbon::parse($user->created_at);
				Log::info($userCreatedAt);
				$isNewUser = $userCreatedAt->diffInHours($now) <= 48; // New user = registered within 48 hours

				// Check if user type is 'new' but user is not a new user
				if ($codeGroup->user_type === array_keys(PromotionCodeGroup::USER_TYPES)[1] && !$isNewUser) { // 'new' is the second key
					return response()->json(array_merge([
						'success' => false,
						'message' => __('messages.success.promotion_code_controller.User_not_eligible'),
					], $responseData), 400);
				}

				// Check if user type is 'old' but user is a new user
				if ($codeGroup->user_type === array_keys(PromotionCodeGroup::USER_TYPES)[2] && $isNewUser) { // 'old' is the third key
					return response()->json(array_merge([
						'success' => false,
						'message' => __('messages.success.promotion_code_controller.User_not_eligible'),
					], $responseData), 400);
				}
			}

			// Check if the product exists in the database
			$product = \App\Models\Product::find($productId);
			if (!$product) {
				return response()->json(array_merge([
					'success' => false,
					'message' => __('messages.success.promotion_code_controller.Product_not_eligible'),
				], $responseData), 400);
			}

			// Check if the product_id is attached to the promotion code group
			$eligibleProductIds = $codeGroup->products()->pluck('products.id')->toArray();

			// Only check product eligibility if the promotion code group has specific products attached
			// If eligibleProductIds is empty, then skip the product checking process
			if (!empty($eligibleProductIds) && !in_array($productId, $eligibleProductIds)) {
				return response()->json(array_merge([
					'success' => false,
					'message' => __('messages.success.promotion_code_controller.Product_not_eligible'),
				], $responseData), 400);
			}

			// Check if the payment_method is attached to the promotion code group
			$eligiblePaymentMethods = $codeGroup->paymentMethods()->pluck('payment_methods.code')->toArray();

			// Only check payment method eligibility if the promotion code group has specific payment methods attached
			// If eligiblePaymentMethods is empty, then skip the payment method checking process
			if (!empty($eligiblePaymentMethods) && !in_array($paymentMethod, $eligiblePaymentMethods)) {
				return response()->json(array_merge([
					'success' => false,
					'message' => __('messages.success.promotion_code_controller.Payment_method_not_eligible'),
				], $responseData), 400);
			}

			// Check if product price is smaller than the discount amount
			if ($codeGroup->use_fix_amount_discount) {
				$productPrice = ($product->discount_price) ?? $product->unit_price;
				
				if ($productPrice < $codeGroup->discount_amount) {
					return response()->json(array_merge([
						'success' => false,
						'message' => __('messages.success.promotion_code_controller.Discount_exceeds_product_price'),
					], $responseData), 400);
				}
			}
			
			// If all checks pass, return success with discount information
			return response()->json(array_merge([
				'success' => true,
				'message' => __('messages.success.promotion_code_controller.Code_applied_successfully'),
			], $responseData));

		} catch (Exception $e) {
			Log::error('[PromotionCodeController] Error checking promo code', [
				'error' => $e->getMessage(),
				'trace' => $e->getTraceAsString(),
			]);

			return response()->json([
				'success' => false,
				'message' => __('messages.success.promotion_code_controller.Code_invalid'),
			], 500);
		}
	}
}
