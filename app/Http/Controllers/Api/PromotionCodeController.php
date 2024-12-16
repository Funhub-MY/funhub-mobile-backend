<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PromotionCode;
use App\Services\PointService;
use App\Services\PointComponentService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PromotionCodeController extends Controller
{
    public function __construct(
        protected PointService $pointService,
        protected PointComponentService $pointComponentService
    ) {}

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        //
    }

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

                // process rewards
                $rewards = $promotionCode->reward;
                $rewardComponents = $promotionCode->rewardComponent;

                foreach ($rewards as $reward) {
                    $this->pointService->credit(
                        $promotionCode,
                        auth()->user(),
                        $reward->pivot->quantity,
                        'Promotion code: ' . $code
                    );
                }

                foreach ($rewardComponents as $component) {
                    $this->pointComponentService->credit(
                        $promotionCode,
                        auth()->user(),
                        $component,
                        $component->pivot->quantity,
                        'Promotion code: ' . $code
                    );
                }

                return response()->json([
                    'message' => 'Code redeemed successfully',
                    'rewards' => $rewards,
                    'reward_components' => $rewardComponents,
                ]);
            });
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'message' => 'Invalid or already redeemed code',
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'An error occurred while redeeming the code',
            ], 500);
        }
    }
}
