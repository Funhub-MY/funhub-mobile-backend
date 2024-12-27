<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PromotionCode;
use App\Services\PointService;
use App\Services\PointComponentService;
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

                $promotionCode = PromotionCode::where('code', $code)
                    ->first();

                Log::info('[PromotionCodeController] Found promotion code', [
                    'promotion_code_id' => $promotionCode->id,
                    'claimed_by_id' => $promotionCode->claimed_by_id
                ]);

                if (!$promotionCode) {
                    return response()->json([
                        'message' => __('messages.success.promotion_code_controller.Invalid_code'),
                    ], 404);
                }

                // check if it's already claimed
                if ($promotionCode->claimed_by_id) {
                    return response()->json([
                        'message' => __('messages.success.promotion_code_controller.Code_already_claimed'),
                    ], 400);
                }

                // check if promotion code group campaign is still active
                if ($promotionCode->promotionCodeGroup) {
                    if (!$promotionCode->promotionCodeGroup->status) {
                        return response()->json([
                            'message' => __('messages.success.promotion_code_controller.Campaign_disabled'),
                        ], 400);
                    }

                    $now = now();
                    if ($promotionCode->promotionCodeGroup->campaign_until && $now->gt($promotionCode->promotionCodeGroup->campaign_until)) {
                        return response()->json([
                            'message' => __('messages.success.promotion_code_controller.Campaign_ended'),
                        ], 400);
                    }

                    if ($promotionCode->promotionCodeGroup->campaign_from && $now->lt($promotionCode->promotionCodeGroup->campaign_from)) {
                        return response()->json([
                            'message' => __('messages.success.promotion_code_controller.Campaign_not_started'),
                        ], 400);
                    }
                }

                // check if promotion code is active
                if (!$promotionCode->isActive()) {
                    return response()->json([
                        'message' => __('messages.success.promotion_code_controller.Code_not_active_or_expired'),
                    ], 400);
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

                return response()->json([
                    'message' => __('messages.success.promotion_code_controller.Code_redeemed_successfully'),
                    'rewards' => $rewards,
                    'reward_components' => $rewardComponents,
                ]);
            });
        } catch (Exception $e) {
            return response()->json([
                'message' => __('messages.success.promotion_code_controller.Invalid_code'),
            ], 404);
        }
    }
}
