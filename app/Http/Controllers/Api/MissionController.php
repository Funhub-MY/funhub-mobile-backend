<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\MissionResource;
use App\Models\Mission;
use App\Models\RewardComponent;
use Illuminate\Http\Request;
use App\Services\PointService;
use App\Services\PointComponentService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class MissionController extends Controller
{
    private $eventMatrix, $pointService, $pointComponentService;

    public function __construct(PointService $pointService, PointComponentService $pointComponentService)
    {
        $this->eventMatrix = config('app.event_matrix');
        $this->pointService = $pointService;
        $this->pointComponentService = $pointComponentService;
    }

    /**
     * Get all missions available.
     * 
     * @return \Illuminate\Http\Resources\Json\AnonymousResourceCollection
     * 
     * @group Mission
     * @response scenario=success {
     * "all_missions_available": [],
     * "missions_participating": [],
     * "missions_completed_by_yet_to_claim": [],
     * "missions_completed": []
     * }
     */
    public function index()
    {
        // get all missions available
        $missions = Mission::enabled()  
            ->get();

        // missions im actively participating
        $user = auth()->user();
        $missionsParticipating = $user->missionsParticipating()
            ->enabled()
            ->wherePivot('is_completed', false)
            ->get();

        $missionsCompletedByYetToClaim = $user->missionsParticipating()
            ->enabled()
            ->wherePivot('current_value' , '>=', DB::raw('missions.value'))
            ->wherePivot('is_completed', false)
            ->get();

        // missions completed
        $missionsCompleted = $user->missionsParticipating()
            ->enabled()
            ->wherePivot('is_completed', true)
            ->get();

        return [
            'all_missions_available' => MissionResource::collection($missions),
            'missions_participating' => MissionResource::collection($missionsParticipating),
            'missions_completed_by_yet_to_claim' => MissionResource::collection($missionsCompletedByYetToClaim),
            'missions_completed' => MissionResource::collection($missionsCompleted)
        ];
    }
    
    /**
     * Complete all missions or single mission
     *
     * @return void
     * 
     * @group Mission
     * @bodyParam mission_id int The id of the mission to complete, if not pass in, system will complete all missions thats eligible to be completed. Example: 1
     * @response scenario=success {
     * "message": "Mission(s) completed successfully.",
     * "completed_missions": [
     *    1, 2
     * ],
     * "reward": {
     *   "object": {},
     *   "quantity": 1
     * }
     * }
     * 
     */
    public function postCompleteMission(Request $request)
    {
        $this->validate($request, [
            'mission_id' => 'nullable|exists:missions,id'
        ]);

        $user = auth()->user();
        $completed_missions = [];

        if ($request->has('mission_id')) {
            // check if user has participated in this mission
            $user->missionsParticipating()->where('mission_id', $request->mission_id)->firstOrFail();
            // complete single mission
            $mission = Mission::find($request->mission_id);
            $this->completeMission($mission, $user);
            $completed_missions[] = $mission->id; 
        } else {
            // complete all missions
            $missions = $user->missionsParticipating()->where('is_completed', false)
                ->wherePivot('current_value', '>=', 'value')
                ->get();

            // disburse rewards
            foreach ($missions as $mission) {
                $this->completeMission($mission, $user);
                $completed_missions[] = $mission->id;
            }
        }

        return response()->json([
            'message' => 'Mission(s) completed successfully.',
            'completed_missions' => $completed_missions,
            'reward' => [
                'object' => $mission->missionable,
                'quantity' => $mission->reward_quantity
            ]
        ]);
    }

    /**
     * Complete a single mission of user
     *
     * @param Mission $mission
     * @param User $user
     * @return void
     */
    private function completeMission($mission, $user)
    {
        // update pivot table
        $user->missionsParticipating()->updateExistingPivot($mission->id, [
            'is_completed' => true,
            'completed_at' => now()
        ]);

        // disburse rewards
        $this->disburseRewards($mission, $user);
    }

    /**
     * Disburse rewards to user
     *
     * @param Mission $mission
     * @param User $user
     * @return void
     */
    private function disburseRewards($mission, $user)
    {
        Log::info('Mission Completed', [
            'mission' => $mission->toArray(),
            'user' => $user->id
        ]);
          if ($mission->missionable_type == Reward::class) {
            // reward point via pointService
            $this->pointService->credit($mission, $user, $mission->reward_quantity, 'Mission Completed - '. $mission->name);
            Log::info('Mission Completed and Disbursed Reward', [
                'mission' => $mission->id,
                'user' => $user->id,
                'reward_type' => 'point',
                'reward' => $mission->reward_quantity
            ]);
          } else if ($mission->missionable_type == RewardComponent::class) {
              // reward point via pointComponentService
            $this->pointComponentService->credit(
                $mission, 
                $mission->missionable, // PointComponent as reward
                $user,
                $mission->reward_quantity,
                'Mission Completed - '. $mission->name
            );
            Log::info('Mission Completed and Disbursed Reward', [
                'mission' => $mission->id,
                'user' => $user->id,
                'reward_type' => 'point component',
                'reward' => $mission->reward_quantity
            ]);
          } else {
             Log::error('Mission Completed but no reward disbursed', [
                 'mission' => $mission->id,
                 'user' => $user->id,
                 'reward_type' => 'none'
             ]);
          }
    }
}
