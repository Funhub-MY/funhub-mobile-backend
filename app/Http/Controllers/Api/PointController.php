<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PointComponentLedger;
use App\Models\PointLedger;
use App\Models\Reward;
use App\Models\RewardComponent;
use App\Services\PointComponentService;
use App\Services\PointService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class PointController extends Controller
{
    protected $pointService, $pointComponentService;

    public function __construct(PointService $pointService, PointComponentService $pointComponentService)
    {
        $this->pointService = $pointService;
        $this->pointComponentService = $pointComponentService;
    }

    /**
     * Get the point balance of the user.
     * 
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\JsonResponse
     * 
     * @throws \Illuminate\Auth\Access\AuthorizationException
     * 
     * @group Point
     * @response scenario=success {
     * "balance": 100
     * }
     */
    public function getPointBalance(Request $request)
    {
        $user = $request->user();

        $latestLedger = PointLedger::where('user_id', $user->id)
            ->orderBy('id', 'desc')
            ->first();

        if ($latestLedger) {
            return response()->json([
                'balance' => $latestLedger->balance
            ]);
        } else {
            return response()->json([
                'balance' => 0
            ]);
        }
    }

    /**
     * Get the point component balance of the user.
     * 
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\JsonResponse
     * 
     * @throws \Illuminate\Auth\Access\AuthorizationException
     * 
     * @group Point
     * @bodyParam type string required The type of point component. Example: egg
     * 
     * @response scenario=success {
     * "type": "egg",
     * "balance": 100
     * }
     * 
     */
    public function getPointComponentBalance(Request $request)
    {
        // validate point component type exists or not
        $request->validate([
            'type' => 'required|exists:reward_components,name'
        ]);

        // search type of reward component
        $component = RewardComponent::where('name', $request->type)->firstOrFail();

        $user = $request->user();

        $latestLedger = PointComponentLedger::where('user_id', $user->id)
            ->where('component_type', RewardComponent::class)
            ->where('component_id', $component->id)
            ->orderBy('id', 'desc')
            ->first();

        if ($latestLedger) {
            return response()->json([
                'type' => $request->type,
                'balance' => $latestLedger->balance
            ]);
        } else {
            return response()->json([
                'type' => $request->type,
                'balance' => 0
            ]);
        }
    }

    /**
     * Combine Points Component to Form a Reward
     * 
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\JsonResponse
     * 
     * @group Point
     * @bodyParam reward_id integer required The id of the reward. Example: 1
     * @bodyParam quantity integer required The quantity of the reward to form. Example: 1
     */
    public function postCombinePoints(Request $request)
    {
        $this->validate($request, [
            'reward_id' => 'required|exists:rewards,id',
            'quantity' => 'required|integer'
        ]);

        $reward = Reward::with('rewardComponents')
            ->where('id', $request->reward_id)
            ->firstOrFail();

        if ($reward->rewardComponents->count() == 0) {
            return response()->json(['message' => 'Reward has no components to form yet'], 422);
        }

        $user = $request->user();

        foreach($reward->rewardComponents as $component) {
            if ($component->pivot->points * $request->quantity > $user->getPointComponentBalance($component)) {
                return response()->json(['message' => 'User does not have enough points to form this reward'], 422);
            }
        }

        try {
            foreach($reward->rewardComponents as $component) {
                $this->pointComponentService->debit($reward, $component, $user->id, $component->pivot->points * $request->quantity, 'Debit for combining to form Reward '. $reward->name);
            }
        } catch (\Exception $e) {
            Log::error($e->getMessage(), ['user_id' => $user->id, 'reward_id' => $reward->id, 'quantity' => $request->quantity]);
            return response()->json(['message' => 'Error while deducting points from user'], 422);
        }

        $this->pointService->credit($reward, $user->id, $request->quantity, 'Reward Formed');
        Log::info('Reward Formed', ['user_id' => $user->id, 'reward_id' => $reward->id, 'quantity' => $request->quantity]);

        return response()->json([
            'latest_point_balance' => $user->point_balance(),
             'message' => 'Reward Formed'
        ]);
    }

    /**
     * Get Rewards Available.
     * 
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\JsonResponse
     * 
     * @throws \Illuminate\Auth\Access\AuthorizationException
     * 
     * @group Point
     * @response scenario=success {
     * "rewards": [
     * {
     * "id": 1,
     * "name": "Funhub",
     * "description": "Funhub",
     * "components": [],
     * }
     * ]
     */
    public function getRewards(Request $request)
    {
        $user = $request->user();

        $rewards = Reward::with('components')
            ->get();

        return response()->json([
            'rewards' => $rewards
        ]);
    }
}
