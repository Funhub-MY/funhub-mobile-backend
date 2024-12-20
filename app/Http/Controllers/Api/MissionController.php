<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\MissionResource;
use App\Models\Mission;
use App\Models\MissionRewardDisbursement;
use App\Models\Reward;
use App\Models\RewardComponent;
use App\Models\User;
use App\Notifications\MissionCompleted;
use App\Notifications\RewardReceivedNotification;
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
     * @urlParam completed_only boolean optional Only show completed missions(is_completed=1). Example: 0
     * @urlParam claimed_only boolean optional Only show claimed missions(claimed_only=1). Example: 0
     * @urlParam frequency string optional Filter by frequency, can combine frquency with multiple comma separated. Example: one-off,daily,monthly,accumulated
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
        $query->when($request->has('claimed_only') && $request->claimed_only == 1, function($query) {
            $query->whereHas('participants', function($query) {
                $query->where('user_id', auth()->user()->id)
                    ->whereNotNull('claimed_at');
            });
        });

        // filter by claimed_only false
        $query->when($request->has('claimed_only') && $request->claimed_only == 0, function($query) {
            $query->where(function($query) {
                $query->whereHas('participants', function($query) {
                    $query->where('user_id', auth()->user()->id)
                        ->whereNull('claimed_at');
                })->orWhereDoesntHave('participants', function($query) {
                    $query->where('user_id', auth()->user()->id);
                });
            });
        });

        // filter by type of mission one-off, daily, monthly
        $query->when($request->has('frequency') && $request->frequency, function($query) use ($request) {
            // check if frequency contains one-off/daily/monthly or mix
            if (!preg_match('/(one-off|daily|monthly|accumulated)/', $request->frequency)) {
                $request->validate([
                    'frequency' => 'in:one-off,daily,monthly,accumulated'
                ]);
            }
            $frequencies = explode(',', $request->frequency);
            $query->whereIn('frequency', $frequencies);
        });

        // when completed_only = 1
        $query->when($request->has('completed_only') && $request->completed_only == 1, function($query) use ($request) {
            $query->whereHas('participants', function($query) use ($request) {
                $query->where('user_id', auth()->user()->id)
                    ->where('missions_users.is_completed', true)
                    ->when($request->frequency === 'daily', function($query) {
                        // For daily missions, only show completed ones from the current day
                        $query->where('missions_users.completed_at', '>=', now()->startOfDay())
                              ->where('missions_users.completed_at', '<=', now()->endOfDay());
                    })
                    ->when($request->frequency === 'monthly', function($query) {
                        // For monthly missions, only show completed ones from the current month
                        $query->where('missions_users.completed_at', '>=', now()->startOfMonth())
                              ->where('missions_users.completed_at', '<=', now()->endOfMonth());
                    })
                    ->when($request->frequency === 'accumulated', function($query) {
                        // For accumulated missions, only show the completed ones that have been claimed
                        $query->whereNotNull('missions_users.claimed_at')
                            ->whereNotExists(function ($subquery) {
                                $subquery->from('missions_users as mu2')
                                    ->whereRaw('mu2.mission_id = missions_users.mission_id')
                                    ->where('mu2.user_id', auth()->user()->id)
                                    ->whereNull('mu2.claimed_at')
                                    ->where('mu2.created_at', '>', 'missions_users.created_at');
                            });
                    });
            });
        });

        // when completed_only = 0
        $query->when($request->has('completed_only') && $request->completed_only == 0, function($query) use ($request) {
            // is not participating, or where is participating and is_completed is false
            $query->where(function ($query) use ($request) {
                $query->whereHas('participants', function($query) use ($request) {
                    $query->where('user_id', auth()->user()->id)
                        ->where('missions_users.is_completed', false)
                        ->when($request->frequency === 'daily', function($query) {
                            // For daily missions, consider previous day's completed missions as not completed
                            $query->where(function($q) {
                                $q->whereNull('missions_users.completed_at')
                                  ->orWhere('missions_users.completed_at', '>=', now()->startOfDay());
                            });
                        })
                        ->when($request->frequency === 'accumulated', function($query) {
                            // For accumulated missions, only show the latest uncompleted/unclaimed one
                            $query->whereNull('missions_users.claimed_at')
                                ->whereNotExists(function ($subquery) {
                                    $subquery->from('missions_users as mu2')
                                        ->whereRaw('mu2.mission_id = missions_users.mission_id')
                                        ->where('mu2.user_id', auth()->user()->id)
                                        ->where('mu2.created_at', '>', 'missions_users.created_at');
                                });
                        });
                })->orWhereDoesntHave('participants', function($query) {
                    $query->where('user_id', auth()->user()->id);
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
            'mission_id' => 'required|exists:missions,id'
        ]);

        $user = auth()->user();
        $completed_missions = [];

        // check if user has participated in this mission, get the latest one
        $user->missionsParticipating()
            ->where('mission_id', $request->mission_id)
            ->firstOrFail();

        // complete single mission
        $mission = Mission::find($request->mission_id);
        $this->completeMission($mission, $user);

        $completed_missions[] = $mission->id;

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
        // if mission is auto disburse, dont disburse rewards
        if ($mission->auto_disburse_rewards) {
            Log::info('Mission '.$mission->id.' is auto disburse but user called complete mission.', [
                'mission' => $mission->id,
                'user' => $user->id,
                'reward_type' => 'auto disburse',
                'reward_quantity' => $mission->reward_quantity
            ]);
            return;
        }

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
        Log::info('Mission Completed, User claim reward', [
            'mission' => $mission->toArray(),
            'user' => $user->id
        ]);

        $hasDisbursement = false;

        if ($mission->missionable_type == Reward::class) {
            // reward point via pointService
            $this->pointService->credit($mission, $user, $mission->reward_quantity, 'Mission Completed - '. $mission->name);
            Log::info('Mission Completed and Disbursed Reward', [
                'mission' => $mission->id,
                'user' => $user->id,
                'reward_type' => 'point',
                'reward' => $mission->reward_quantity
            ]);

            $hasDisbursement = true;
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

            $hasDisbursement = true;
        } else {
            Log::error('Mission Completed but no reward disbursed', [
                'mission' => $mission->id,
                'user' => $user->id,
                'reward_type' => 'none'
            ]);
        }

        if ($hasDisbursement) {
            // if mission is not auto disbursed, also update claimed_at
            if (!$mission->auto_disburse_rewards) {
                MissionRewardDisbursement::create([
                    'mission_id' => $mission->id,
                    'user_id' => $user->id,
                    'reward_quantity' => $mission->reward_quantity
                ]);

                // fire mission rewarded notification
                try {
                    $locale = $user->last_lang ?? config('app.locale');
                    $user->notify((new RewardReceivedNotification(
                        $mission->missionable,
                        $mission->reward_quantity,
                        $user,
                        $mission->name,
                        $mission
                    ))->locale($locale));
                } catch (\Exception $e) {
                    Log::error('Reward Received Notification Error', [
                        'mission_id' => $mission->id,
                        'user' => $user->id,
                        'error' => $e->getMessage()
                    ]);
                }

                // update user mission ensure claimed_at is updated based on mission frequency
                if ($mission->frequency == 'one-off') {
                    Log::info('Mission Completed, User claim reward, One-Off', [
                        'mission' => $mission->id,
                        'user' => $user->id,
                    ]);
                    $user->missionsParticipating()
                        ->wherePivot('mission_id', $mission->id)
                        ->wherePivot('claimed_at', null)
                        ->orderByDesc('id')
                        ->limit(1)
                        ->update([
                            'claimed_at' => now(),
                        ]);
                } elseif ($mission->frequency == 'daily') {
                    Log::info('Mission Completed, User claim reward, Daily', [
                        'mission' => $mission->id,
                        'user' => $user->id,
                    ]);
                    $user->missionsParticipating()
                        ->wherePivot('mission_id', $mission->id)
                        ->wherePivot('claimed_at', null)
                        ->where('missions_users.created_at', '>=', now()->startOfDay())
                        ->where('missions_users.created_at', '<', now()->endOfDay())
                        ->orderByDesc('missions_users.created_at')
                        ->limit(1)
                        ->update([
                            'claimed_at' => now(),
                        ]);
                } elseif ($mission->frequency == 'monthly') {
                    Log::info('Mission Completed, User claim reward, Monthly', [
                        'mission' => $mission->id,
                        'user' => $user->id,
                    ]);
                    $user->missionsParticipating()
                        ->wherePivot('mission_id', $mission->id)
                        ->wherePivot('claimed_at', null)
                        ->where('missions_users.created_at', '>=', now()->startOfMonth())
                        ->where('missions_users.created_at', '<', now()->endOfMonth())
                        ->orderByDesc('missions_users.created_at')
                        ->limit(1)
                        ->update([
                            'claimed_at' => now(),
                        ]);
                } elseif ($mission->frequency == 'accumulated') {
                    Log::info('Mission Completed, User claim reward, Accumulated', [
                        'mission' => $mission->id,
                        'user' => $user->id,
                    ]);
                    $user->missionsParticipating()
                        ->wherePivot('mission_id', $mission->id)
                        ->wherePivot('claimed_at', null)
                        ->orderByDesc('missions_users.id')
                        ->limit(1)
                        ->update([
                            'claimed_at' => now(),
                        ]);
                }
            }
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

    /**
     * Get enabled mission frequencies
     *
     * @return MissionResource
     *
     * @group Mission
     * @response scenario=success {
     * "data": [
     *  "one-off",
     *  "daily",
     *  "monthly",
     *  "accumulated"
     * ]
     * }
     */
    public function getEnabledMissionFrequency()
    {
        $frequencies = Mission::enabled()
            ->select('frequency')
            ->distinct()
            ->pluck('frequency')
            ->toArray();

        return response()->json([
            'data' => $frequencies
        ]);
    }
}
