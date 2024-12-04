<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\MissionResource;
use App\Models\Mission;
use App\Services\MissionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class MissionController extends Controller
{
    protected MissionService $missionService;
    protected array $eventMatrix;

    public function __construct(MissionService $missionService)
    {
        $this->missionService = $missionService;
        $this->eventMatrix = config('app.event_matrix');
    }

    /**
     * Get all missions available.
     *
     * @return MissionResource
     *
     * @throws \Illuminate\Auth\Access\AuthorizationException
     *
     * @group Mission
     * @urlParam completed_only boolean optional Filter by completed only. Example: 0
     * @urlParam claimed_only boolean optional Filter by claimed only. Example: 0
     * @response scenario=success {
     * "current_page": 1,
     * "data": []
     * }
     */
    public function index(Request $request)
    {
        $query = Mission::enabled()
            ->with(['participants' => function($query) {
                $query->where('user_id', auth()->user()->id);
            }])
            ->with(['predecessors' => function($query) {
                $query->with(['participants' => function($query) {
                    $query->where('user_id', auth()->user()->id);
                }]);
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

        // filter by frequency
        $query->when($request->has('frequency') && $request->frequency, function($query) use ($request) {
            if (!preg_match('/(one-off|daily|monthly|accumulated)/', $request->frequency)) {
                $request->validate([
                    'frequency' => 'in:one-off,daily,monthly,accumulated'
                ]);
            }
            $frequencies = explode(',', $request->frequency);
            $query->whereIn('frequency', $frequencies);
        });

        // completed_only = 1
        $query->when($request->has('completed_only') && $request->completed_only == 1, function($query) use ($request) {
            $query->whereHas('participants', function($query) use ($request) {
                $query->where('user_id', auth()->user()->id)
                    ->where('missions_users.is_completed', true)
                    ->when($request->frequency === 'daily', function($query) {
                        $query->where('missions_users.completed_at', '>=', now()->startOfDay())
                              ->where('missions_users.completed_at', '<=', now()->endOfDay());
                    })
                    ->when($request->frequency === 'monthly', function($query) {
                        $query->where('missions_users.completed_at', '>=', now()->startOfMonth())
                              ->where('missions_users.completed_at', '<=', now()->endOfMonth());
                    })
                    ->when($request->frequency === 'accumulated', function($query) {
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

        // completed_only = 0
        $query->when($request->has('completed_only') && $request->completed_only == 0, function($query) use ($request) {
            $query->where(function ($query) use ($request) {
                $query->whereHas('participants', function($query) use ($request) {
                    $query->where('user_id', auth()->user()->id)
                        ->where('missions_users.is_completed', false)
                        ->when($request->frequency === 'daily', function($query) {
                            $query->where(function($q) {
                                $q->whereNull('missions_users.completed_at')
                                  ->orWhere('missions_users.completed_at', '>=', now()->startOfDay());
                            });
                        })
                        ->when($request->frequency === 'accumulated', function($query) {
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
     * Complete mission and disburse reward
     */
    public function postCompleteMission(Request $request)
    {
        $request->validate([
            'mission_id' => 'required|exists:missions,id'
        ]);

        try {
            $user = auth()->user();
            $mission = Mission::findOrFail($request->mission_id);

            // Verify participation
            $userMission = $user->missionsParticipating()
                ->where('mission_id', $mission->id)
                ->firstOrFail();

            if ($mission->auto_disburse_rewards) {
                Log::info('Mission is auto-disbursed, manual completion not allowed', [
                    'mission_id' => $mission->id,
                    'user_id' => $user->id
                ]);
                return response()->json([
                    'message' => __('messages.error.mission_controller.Mission_is_auto_disbursed')
                ], 422);
            }

            if (!$userMission->pivot->is_completed) {
                return response()->json([
                    'message' => __('messages.error.mission_controller.Mission_not_completed')
                ], 422);
            }

            $this->missionService->disburseReward($mission, $user);

            return response()->json([
                'message' => __('messages.success.mission_controller.Mission(s)_completed_successfully'),
                'completed_missions' => [$mission->id],
                'reward' => [
                    'object' => $mission->missionable,
                    'quantity' => $mission->reward_quantity
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Error completing mission', [
                'mission_id' => $request->mission_id,
                'user_id' => auth()->id(),
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'message' => __('messages.error.mission_controller.Failed_to_complete_mission')
            ], 500);
        }
    }

    /**
     * Get claimable missions
     */
    public function getClaimableMissions()
    {
        $user = auth()->user();

        $missions = $user->missionsParticipating()
            ->whereNull('missions_users.completed_at')
            ->orderByPivot('updated_at', 'desc')
            ->paginate(config('app.paginate_per_page'));

        $missions->getCollection()->transform(function($mission) {
            $values = json_decode($mission->values, true);
            $currentValues = json_decode($mission->pivot->current_values, true);

            $mission->claimable = collect($mission->events)
                ->every(function($event) use ($currentValues, $values) {
                    return isset($currentValues[$event]) && $currentValues[$event] >= $values[$event];
                });

            return $mission;
        });

        return MissionResource::collection($missions);
    }
}
