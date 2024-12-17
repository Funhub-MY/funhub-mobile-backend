<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PromotionCode;
use App\Services\PointService;
use App\Services\PointComponentService;
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

    public function redeem(Request $request)
    {
        $request->validate([
            'code' => 'required|string',
        ]);

        try {
            return DB::transaction(function () use ($request) {
                $code = $request->input('code');
                $promotionCode = PromotionCode::where('code', $code)
                    ->where('is_redeemed', false)
                    ->firstOrFail();

                Log::info('[PromotionCodeController] Found promotion code', [
                    'promotion_code_id' => $promotionCode->id,
                    'claimed_by_id' => $promotionCode->claimed_by_id
                ]);

                // check if it's already claimed
                if ($promotionCode->claimed_by_id) {
                    return response()->json([
                        'message' => 'This code has already been claimed',
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
                    'message' => '[PromotionCodeController] Code redeemed successfully',
                    'rewards' => $rewards,
                    'reward_components' => $rewardComponents,
                ]);
            });
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'message' => 'Invalid or already redeemed code',
            ], 404);
        }
    }
}
