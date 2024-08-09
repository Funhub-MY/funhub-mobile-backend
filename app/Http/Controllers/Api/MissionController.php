<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\MissionResource;
use App\Models\Mission;
use App\Models\RewardComponent;
use App\Models\User;
use App\Notifications\MissionCompleted;
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
     * @urlParam completed_only boolean optional Only show completed missions(is_completed=true). Example: false
     * @urlParam claimed_only boolean optional Only show claimed missions(is_completed=true). Example: false
     * @urlParam frequency string optional Filter by frequency, can combine frquency with multiple comma separated. Example: one-off,daily,monthly
     * @response scenario=success {
     * "current_page": 1,
     * "data": [
     *   {
     *   "id": 1,
     *   "name": "Complete 10 missions",
     *   "is_participating": true,
     *   "description": "Complete 10 missions to earn a reward",
     *   "events": ["mission_completed"],
     *   "current_values": {"mission_completed": 5},
     *   "values": {"mission_completed": 10},
     *   "reward": {
     *       "id": 1,
     *       "name": "Egg",
     *       "description": "egg",
     *       "thumbnail_url": "https://example.com/egg.png",
     *   },
     *   "reward_quantity": 1,
     *   "claimed": false,
     *   "claimed_at": null,
     *   "claimed_at_formatted": null,
     *   "claimed_at_ago": null
     *  }
     * ],
     * "first_page_url": "http://localhost:8000/api/missions?page=1",
     * "from": 1,
     * "last_page": 1,
     * "last_page_url": "http://localhost:8000/api/missions?page=1",
     * "next_page_url": null,
     * "path": "http://localhost:8000/api/missions",
     * "per_page": 15,
     * }
     */
    public function index(Request $request)
    {
        // get all missions available with participants select only auth user
        $query = Mission::enabled()
            ->with(['participants' => function($query) {
                $query->where('user_id', auth()->user()->id);
            }])
            ->orderBy('created_at', 'desc');

        // filter by claimed_only
        $query->when($request->has('claimed_only') && $request->claimed_only, function($query) {
            $query->whereHas('participants', function($query) {
                $query->where('user_id', auth()->user()->id)
                    ->whereNotNull('missions_users.claimed_at');
            });
        });

        // filter by type of mission one-off, daily, monthly
        $query->when($request->has('frequency') && $request->frequency, function($query) use ($request) {
            // check if frequency contains one-off/daily/monthly or mix
            if (!preg_match('/(one-off|daily|monthly)/', $request->frequency)) {
                $request->validate([
                    'frequency' => 'in:one-off,daily,monthly'
                ]);
            }
            $frequencies = explode(',', $request->frequency);
            $query->whereIn('frequency', $frequencies);
        });

        // when completed_only = 1
        $query->when($request->has('completed_only') && $request->completed_only && $request->completed_only == 1, function($query) {
            $query->whereHas('participants', function($query) {
                $query->where('user_id', auth()->user()->id)
                    ->where('missions_users.is_completed', true);
            });
        });

        // when completed_only = 0
        $query->when($request->has('completed_only') && $request->completed_only && $request->completed_only == 0, function($query) {
            $query->where(function($query) {
                $query->whereDoesntHave('participants', function($query) {
                    $query->where('user_id', auth()->user()->id);
                })
                ->orWhereHas('participants', function($query) {
                    $query->where('user_id', auth()->user()->id)
                        ->where('missions_users.is_completed', false);
                });
            });
        });

        $missions = $query->paginate(config('app.paginate_per_page'));

        return MissionResource::collection($missions);
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
                ->whereRaw('JSON_CONTAINS(current_values, \'true\', "$")')
                ->get();

            // disburse rewards
            foreach ($missions as $mission) {
                $this->completeMission($mission, $user);
                $completed_missions[] = $mission->id;
            }
        }

        return response()->json([
            'message' => __('messages.success.mission_controller.Mission(s)_completed_successfully'),
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
            'completed_at' => now(),
        ]);
        try {
            $locale = auth()->user()->last_lang ?? config('app.locale');
            auth()->user()->notify((new MissionCompleted($mission, $user, $mission->missionable->name, $mission->reward_quantity))->locale($locale));
        } catch (\Exception $e) {
            Log::error('Mission Completed Notification Error', [
                'mission_id' => $mission->id,
                'user' => $user->id,
                'error' => $e->getMessage()
            ]);
        }

        // disburse rewards if auto_disburse_rewards is true
        if ($mission->auto_disburse_rewards) {
            $this->disburseRewards($mission, $user);
        }
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


            // if mission is not auto disbursed, also update claimed_at
            if (!$mission->auto_disburse_rewards) {
                $user->missionsParticipating()->updateExistingPivot($mission->id, [
                    'claimed_at' => now(),
                ]);
            }
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

            // if mission is not auto disbursed, also update claimed_at
            if (!$mission->auto_disburse_rewards) {
                $user->missionsParticipating()->updateExistingPivot($mission->id, [
                    'claimed_at' => now(),
                ]);
            }
        } else {
            Log::error('Mission Completed but no reward disbursed', [
                'mission' => $mission->id,
                'user' => $user->id,
                'reward_type' => 'none'
            ]);
        }
    }

    /**
     * Get latest claimable missions
     *
     * @return MissionResource
     *
     * @group Mission
     * @response scenario=success {
     * "data": [
     *  {
     *  "id": 1,
     *  "name": "Mission 1",
     *  ...
     *  }
     * ]
     * }
     */
    public function getClaimableMissions()
    {
        $user = auth()->user();

        $claimableMissions = $user->missionsParticipating()
        ->whereNull('missions_users.completed_at')
        ->orderByPivot('updated_at', 'desc')
        ->paginate(config('app.paginate_per_page'));

        // filter each see if mission is claimable by comparing missions values to current_values
        // current_values eg. {"comment_created": 1}, mission's events ['comment_created'] and values {1}
        $claimableMissions->getCollection()->transform(function($mission) {
            $current_values = json_decode($mission->pivot->current_values, true);
            $values = json_decode($mission->values, true);

            $claimable = true;
            foreach (json_decode($mission->events) as $event) {
                if (!isset($current_values[$event]) || $current_values[$event] < $values[$event]) {
                    $claimable = false;
                    break;
                }
            }

            $mission->claimable = $claimable;
            return $mission;
        });

        return MissionResource::collection($claimableMissions);
    }
}
