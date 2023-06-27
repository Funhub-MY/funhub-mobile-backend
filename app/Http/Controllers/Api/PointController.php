<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\PointComponentLedgerResource;
use App\Http\Resources\PointLedgerResource;
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
     * Get the point & point components balance of the logged in user.
     * 
     * @return \Illuminate\Http\JsonResponse
     * 
     * @group Point
     * @response scenario=success {
     * "point_balance": { "id": 1, "name": 'Funhub', "thumbnail_url": 'http://localhost:8000/storage/rewards/1/1.jpg', "balance": 100 },
     * "point_components": {[
     *   {"id": 1, "name": "rice", "thumbnail_url": 'http://localhost:8000/storage/rewards/1/1.jpg', "balance": 100},
     *   {"id": 2, "name": "egg", "thumbnail_url": 'http://localhost:8000/storage/rewards/1/1.jpg', "balance": 100},
     *   {"id": 3, "name": "vegetable", "thumbnail_url": 'http://localhost:8000/storage/rewards/1/1.jpg', "balance": 100},
     *   {"id": 4, "name": "meat", "thumbnail_url": 'http://localhost:8000/storage/rewards/1/1.jpg', "balance": 100},
     *   {"id": 5, "name": "fish", "thumbnail_url": 'http://localhost:8000/storage/rewards/1/1.jpg', "balance": 100},
     * ]}
     * }
     */
    public function getPointsBalanceByUser()
    {
        $user = auth()->user();

        // reward
        $reward = Reward::first();

        $point = PointLedger::where('user_id', $user->id)
            ->orderBy('id', 'desc')
            ->first();

        // get current available reward components
        $rewardComponents = RewardComponent::all();
        // map name as key and balance as value
        $pointComponents = [];
        foreach($rewardComponents as $component) {
            $balance = PointComponentLedger::where('user_id', $user->id)
                ->where('component_type', RewardComponent::class)
                ->where('component_id', $component->id)
                ->orderBy('id', 'desc')
                ->first();

            if ($balance) {
                $pointComponents[] = [
                    'id' => $component->id,
                    'name' => $component->name,
                    'thumbnail_url' => $component->thumbnail_url,
                    'balance' => $balance->balance
                ];
            } else {
                $pointComponents[] = [
                    'id' => $component->id,
                    'name' => $component->name,
                    'thumbnail_url' => $component->thumbnail_url,
                    'balance' => 0
                ];
            }
        }

        return response()->json([
            'point_balance' => [
                'id' => $reward->id,
                'name' => $reward->name,
                'thumbnail_url' => $reward->thumbnail_url,
                'balance' => $point ? $point->balance : 0
            ],
            'point_components' => $pointComponents
        ]);
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
     * @bodyParam quantity integer required The quantity of the reward to form. Example: 1
     */
    public function postCombinePoints(Request $request)
    {
        $this->validate($request, [
            'quantity' => 'required|integer'
        ]);

        // get the first reward
        $reward = Reward::with('rewardComponents')
            ->firstOrFail();

        if ($reward->rewardComponents->count() == 0) {
            return response()->json(['message' => 'Reward has no components to form yet'], 422);
        }

        $user = $request->user();

        foreach($reward->rewardComponents as $component) {
            if ($component->pivot->points * $request->quantity > $user->getPointComponentBalance($component)) {
                return response()->json([
                    'message' => 'User does not have enough points to form this reward',
                    'component' => $component->name,
                    'required_balance' => $component->pivot->points * $request->quantity,
                ], 422);
            }
        }

        try {
            foreach($reward->rewardComponents as $component) {
                // debit from point componenet
                $this->pointComponentService->debit($reward, $component, $user->id, $component->pivot->points * $request->quantity, 'Debit for combining to form Reward'. $reward->name);
            }
        } catch (\Exception $e) {
            Log::error($e->getMessage(), ['user_id' => $user->id, 'reward_id' => $reward->id, 'quantity' => $request->quantity]);
            return response()->json(['message' => 'Error while deducting points from user'], 422);
        }

        // credit to reward
        $this->pointService->credit($reward, $user->id, $request->quantity, 'Reward Formed');
        Log::info('Reward Formed', ['user_id' => $user->id, 'reward_id' => $reward->id, 'quantity' => $request->quantity]);

        return response()->json([
            'point_balance' => $user->point_balance(),
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

    /**
     * Get Point Ledger
     *
     * @param Request $request
     * @return PointLedgerResource
     * 
     * @group Point
     * @response scenario=success {
     * "data": [
     * {
     *   "id": 1,
     *   ...
     * }
     * ]
     * }
     */
    public function getPointLedger(Request $request)
    {
        $user = $request->user();

        $pointLedgers = PointLedger::where('user_id', $user->id)
            ->orderBy('created_at', 'desc')
            ->paginate(config('paginate_per_page'));

        return PointLedgerResource::collection($pointLedgers);
    }

    /**
     * Get Point Component Ledgers)
     *
     * @param Request $request
     * @return PointComponentLedgerResource
     * 
     * @group Point
     * @urlParam filter_type string Type of Component (name). Example: egg
     * @response scenario=success {
     * "data": [
     * {
     *   "id": 1,
     *   ...
     * }
     * ]
     * }
     */
    public function getPointComponentLedger(Request $request)
    {
        $user = $request->user();

        // get RewardComponent IDs by name
        $componentIds = RewardComponent::where('name', $request->filter_type)->pluck('id');

        $pointComponentLedgers = PointComponentLedger::where('user_id', $user->id)
            ->orderBy('created_at', 'desc')
            ->paginate(config('paginate_per_page'))
            ->when($request->filter_type, function ($query) use ($componentIds) {
                return $query->where('component_type', RewardComponent::class)
                    ->whereIn('component_id', $componentIds);
            });

        return PointComponentLedgerResource::collection($pointComponentLedgers);
    }
}
