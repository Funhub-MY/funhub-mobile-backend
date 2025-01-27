<?php

namespace App\Services;

use App\Models\Mission;
use App\Models\MissionRewardDisbursement;
use App\Models\User;
use App\Events\MissionProgressUpdated;
use App\Notifications\MissionCompleted;
use App\Notifications\RewardReceivedNotification;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class MissionService
{
    protected PointService $pointService;
    protected PointComponentService $pointComponentService;
    protected $currentUser;

    public function __construct(
        PointService $pointService,
        PointComponentService $pointComponentService
    ) {
        $this->pointService = $pointService;
        $this->pointComponentService = $pointComponentService;
        $this->currentUser = auth()->user(); // possible null but it will be assigned a user when receive an event
    }

    /**
     * Handle mission progress for an event
     */
    public function handleEvent(string $eventType, User $user, array $context = []): void
    {
        try {
            DB::beginTransaction();

            // Spam checks are now handled in the event listener
            $missions = $this->getEligibleMissions($eventType, $user);

            foreach ($missions as $mission) {
                $this->processMissionProgress($mission, $user, $eventType);
            }

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to handle mission event', [
                'event' => $eventType,
                'user_id' => $user->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }

    /**
     * Get eligible missions for the event
     */
    private function getEligibleMissions(string $eventType, User $user): \Illuminate\Database\Eloquent\Collection
    {
        // first get missions that have no predecessors
        $missionsWithNoPredecessors = Mission::enabled()
            ->whereJsonContains('events', $eventType)
            ->whereDoesntHave('predecessors')
            ->with(['participants' => function ($query) use ($user) {
                $query->where('user_id', $user->id);
            }]);

        // then get missions whose predecessors are all completed
        $missionsWithCompletedPredecessors = Mission::enabled()
            ->whereJsonContains('events', $eventType)
            ->whereHas('predecessors')
            ->whereDoesntHave('predecessors.participants', function ($query) use ($user) {
                $query->where('user_id', $user->id)
                    ->where(function ($q) {
                        $q->where('mission_users.is_completed', false)
                            ->orWhereNull('mission_users.is_completed');
                    });
            })
            ->with(['participants' => function ($query) use ($user) {
                $query->where('user_id', $user->id);
            }]);

        // combine both queries and filter by mission eligibility
        return $missionsWithNoPredecessors
            ->union($missionsWithCompletedPredecessors)
            ->get()
            ->filter(function ($mission) use ($user) {
                return $this->isMissionEligible($mission, $user);
            });
    }

    /**
     * Check if mission is eligible for user based on frequency and completion status
     */
    private function isMissionEligible(Mission $mission, User $user): bool
    {
        $latestParticipation = $mission->participants()
            ->where('user_id', $user->id)
            ->orderByDesc('missions_users.created_at')
            ->first();

        if (!$latestParticipation) {
            Log::info('No participation found, mission is eligible');
            return true;
        }

        $startedAt = $latestParticipation->pivot->started_at
            ? Carbon::parse($latestParticipation->pivot->started_at)
            : null;

        $isEligible = match ($mission->frequency) {
            'one-off' => !$latestParticipation->pivot->is_completed,
            'daily' => !$startedAt || // No start date
                !$startedAt->isToday() || // Not started today (new day)
                ($startedAt->isToday() && !$latestParticipation->pivot->is_completed), // started today but not completed
            'accumulated' => !$latestParticipation->pivot->is_completed || 
                ($latestParticipation->pivot->is_completed && $latestParticipation->pivot->claimed_at),
            'monthly' => !$latestParticipation->pivot->completed_at || !Carbon::parse($latestParticipation->pivot->completed_at)->isSameMonth(now()),
            default => true,
        };

        // Log::info('Mission eligibility check result', [
        //     'mission_id' => $mission->id,
        //     'frequency' => $mission->frequency,
        //     'is_eligible' => $isEligible,
        //     'started_at' => $startedAt ? $startedAt->toDateTimeString() : null,
        //     'is_completed' => $latestParticipation->pivot->is_completed,
        //     'claimed_at' => $latestParticipation->pivot->claimed_at,
        //     'latest_participation_id' => $latestParticipation->pivot->id,
        //     'is_today' => $startedAt ? $startedAt->isToday() : null
        // ]);

        return $isEligible;
    }

    /**
     * Process mission progress
     */
    private function processMissionProgress(Mission $mission, User $user, string $eventType): void
    {
        $this->currentUser = $user; // update current user when processing mission
        
        Log::info('Processing mission progress', [
            'mission_id' => $mission->id,
            'user_id' => $user->id,
            'event_type' => $eventType
        ]);

        $userMission = $this->getOrCreateUserMission($mission, $user, $eventType);

        if (!$userMission) {
            Log::error('Failed to get or create user mission', [
                'mission_id' => $mission->id,
                'user_id' => $user->id,
                'event_type' => $eventType
            ]);
            return;
        }

        if (!$userMission->pivot) {
            Log::error('Mission pivot relationship not found', [
                'mission_id' => $mission->id,
                'user_id' => $user->id,
                'event_type' => $eventType
            ]);
            return;
        }

        // For accumulated missions, check if latest instance is completed and claimed
        if ($mission->frequency === 'accumulated' && 
            $userMission->pivot->is_completed && 
            $userMission->pivot->claimed_at) {
            
            Log::info('Creating new accumulated mission instance - previous one completed and claimed', [
                'mission_id' => $mission->id,
                'user_id' => $user->id,
                'previous_instance_id' => $userMission->pivot->id
            ]);

            // Create new progress record with initial values
            $initialValues = collect($mission->events)
                ->mapWithKeys(fn ($event) => [$event => 0])
                ->toArray();

            $user->missionsParticipating()->attach($mission->id, [
                'started_at' => now(),
                'current_values' => json_encode($initialValues),
                'is_completed' => false,
                'completed_at' => null,
                'claimed_at' => null
            ]);

            // Get the newly created mission instance
            $userMission = $user->missionsParticipating()
                ->where('mission_id', $mission->id)
                ->orderByPivot('id', 'desc')
                ->first();

            Log::info('New accumulated mission instance created', [
                'mission_id' => $mission->id,
                'user_id' => $user->id,
                'new_instance_id' => $userMission->pivot->id,
                'is_completed' => $userMission->pivot->is_completed,
                'claimed_at' => $userMission->pivot->claimed_at,
                'current_values' => $userMission->pivot->current_values
            ]);
        }

        // for accumulated missions, we allow updates if:
        // 1. Mission is not completed, or
        // 2. Mission is completed but not claimed (for manual claim missions)
        $shouldUpdate = 
            !$userMission->pivot->is_completed || 
            ($mission->frequency === 'accumulated' && !$userMission->pivot->claimed_at) ||
            ($mission->frequency === 'daily' && Carbon::parse($userMission->pivot->started_at)->isToday()) ||
            ($mission->frequency === 'monthly' && Carbon::parse($userMission->pivot->started_at)->isSameMonth(now()));

        if ($shouldUpdate) {
            $this->updateProgress($userMission, $mission, $eventType);
        } else {
            Log::info('Skipping progress update - mission already completed', [
                'mission_id' => $mission->id,
                'user_id' => $user->id,
                'frequency' => $mission->frequency,
                'is_completed' => $userMission->pivot->is_completed,
                'claimed_at' => $userMission->pivot->claimed_at
            ]);
        }
    }

    /**
     * Get existing or create new user mission progress
     */
    public function getOrCreateUserMission(Mission $mission, User $user, string $eventType)
    {
        Log::info('Getting or creating user mission', [
            'mission_id' => $mission->id,
            'user_id' => $user->id,
            'event_type' => $eventType,
            'frequency' => $mission->frequency,
            'now' => now()->toDateTimeString()
        ]);

        // check if all predecessors are completed before creating new mission
        if (!$this->arePredecessorsCompleted($user, $mission)) {
            Log::info('Cannot start mission - prerequisites not completed', [
                'mission_id' => $mission->id,
                'user_id' => $user->id,
                'frequency' => $mission->frequency
            ]);
            return false;
        }

        // get current active mission progress
        $userMissionQuery = $user->missionsParticipating()
            ->where('mission_id', $mission->id)
            ->where(function ($query) use ($mission) {
                $query->where('is_completed', false)
                    ->when($mission->frequency === 'accumulated', function ($query) {
                        $query->orWhere(function($q) {
                            $q->where('is_completed', true)
                                ->whereNull('claimed_at');
                        })->orWhere(function($q) {
                            $q->where('is_completed', true)
                                ->whereNotNull('claimed_at')
                                ->whereRaw('missions_users.id = (SELECT id FROM missions_users WHERE mission_id = missions_users.mission_id AND user_id = missions_users.user_id ORDER BY created_at DESC LIMIT 1)');
                        });
                    });
            })
            ->when($mission->frequency === 'daily', function($query) {
                return $query->where('started_at', '>=', now()->startOfDay());
            })
            ->when($mission->frequency === 'monthly', function($query) {
                return $query->where('started_at', '>=', now()->startOfMonth());
            });

        if ($mission->frequency === 'accumulated') {
            $userMissionQuery->orderByDesc('missions_users.id'); // get latest started record by id
        } else {
            $userMissionQuery->orderBy('missions_users.created_at', 'desc');
        }

        $userMission = $userMissionQuery->first();

        if (!$userMission) {

            Log::info('Creating new mission progress', [
                'mission_id' => $mission->id,
                'user_id' => $user->id,
                'frequency' => $mission->frequency
            ]);

            $currentValues = collect($mission->events)
                ->mapWithKeys(fn ($event) => [$event => 0])
                ->toArray();

            DB::transaction(function() use ($mission, $user, $currentValues) {
                // for daily/monthly missions, mark previous incomplete records as expired
                if (in_array($mission->frequency, ['daily', 'monthly'])) {
                    $user->missionsParticipating()
                        ->where('mission_id', $mission->id)
                        ->where('is_completed', false)
                        ->update([
                            'missions_users.is_completed' => true,
                            'missions_users.completed_at' => now(),
                        ]);
                }

                // create new progress record
                $user->missionsParticipating()->attach($mission->id, [
                    'started_at' => now(),
                    'current_values' => json_encode($currentValues),
                    'is_completed' => false,
                    'completed_at' => null,
                    'claimed_at' => null
                ]);
            });

            // Get the newly created mission progress
            $userMission = $user->missionsParticipating()
                ->where('mission_id', $mission->id)
                ->orderByDesc('missions_users.created_at')
                ->first();

            Log::info('New mission progress created', [
                'mission_id' => $mission->id,
                'user_id' => $user->id,
                'mission_data' => $userMission
            ]);

            // Initialize the current values for the new mission progress
            if ($userMission && $userMission->pivot) {
                $userMission->pivot->current_values = json_encode($currentValues);
                $userMission->pivot->save();

                // Send mission started notification when creating new progress
                $this->sendMissionStartedNotification($mission, $this->currentUser);
            } else {
                Log::error('Failed to initialize mission progress - pivot not found', [
                    'mission_id' => $mission->id,
                    'user_id' => $user->id,
                    'event_type' => $eventType
                ]);
                return null;
            }
        }

        return $userMission;
    }

    /**
     * Update mission progress and check completion
     */
    private function updateProgress($userMission, Mission $mission, string $eventType): void
    {
        // check if all predecessors are completed before allowing progress update
        if (!$this->arePredecessorsCompleted($userMission->user, $mission)) {
            Log::info('Skipping progress update - prerequisites not completed', [
                'mission_id' => $mission->id,
                'user_id' => $userMission->user->id,
                'frequency' => $mission->frequency
            ]);
            return;
        }

        Log::info('Starting progress update', [
            'mission_id' => $mission->id,
            'event_type' => $eventType,
            'before_values' => $userMission->pivot->current_values,
            'mission_frequency' => $mission->frequency,
            'is_completed' => $userMission->pivot->is_completed,
            'claimed_at' => $userMission->pivot->claimed_at
        ]);

        $currentValues = json_decode($userMission->pivot->current_values, true);
        $oldCount = $currentValues[$eventType] ?? 0;
        $currentValues[$eventType] = $oldCount + 1;

        try {
            DB::transaction(function() use ($userMission, $mission, $currentValues, $eventType, $oldCount) {
                // Update current values first
                $userMission->pivot->current_values = json_encode($currentValues);
                $userMission->pivot->save();

                Log::info('Progress updated', [
                    'mission_id' => $mission->id,
                    'event_type' => $eventType,
                    'old_count' => $oldCount,
                    'new_count' => $currentValues[$eventType],
                    'pivot_id' => $userMission->pivot->id,
                    'is_completed' => $userMission->pivot->is_completed,
                    'claimed_at' => $userMission->pivot->claimed_at
                ]);

                if ($this->checkMissionCompletion($mission, $currentValues)) {
                    Log::info('Mission completion check passed', [
                        'mission_id' => $mission->id,
                        'current_values' => $currentValues,
                        'required_value' => $mission->values[0],
                        'frequency' => $mission->frequency
                    ]);

                    // Only update completion status and disburse rewards if mission is not already completed and claimed
                    if (!$userMission->pivot->is_completed || !$userMission->pivot->claimed_at) {
                        // For accumulated missions, we update both is_completed and claimed_at when auto-disbursing
                        $updateData = [
                            'is_completed' => true,
                            'completed_at' => now(),
                        ];

                        if ($mission->auto_disburse_rewards) {
                            $updateData['claimed_at'] = now();
                        }

                        // Update completion status directly
                        $userMission->pivot->update($updateData);

                        Log::info('Mission completed', [
                            'mission_id' => $mission->id,
                            'user_id' => $userMission->user_id,
                            'missionUser' => $userMission,
                        ]);

                        // Send mission completed notification
                        $this->sendMissionCompletedNotification($mission, $this->currentUser);

                        // Handle auto-disbursement if enabled
                        if ($mission->auto_disburse_rewards) {
                            $this->disburseReward($mission, $this->currentUser);
                        }
                    }
                }
            });

            // Refresh the relationship after transaction
            // $userMission->unsetRelation('pivot');
            $userMission = $userMission->user->missionsParticipating()
                ->where('mission_id', $mission->id)
                ->orderByDesc('missions_users.id')
                ->first();

            Log::info('Mission status after update', [
                'mission_id' => $mission->id,
                'mission_data' => $mission,
                'usermission_data' => $userMission
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to update mission progress', [
                'mission_id' => $mission->id,
                'error' => $e->getMessage(),
                'stack_trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }

    /**
     * Check if mission is completed based on current values
     */
    private function checkMissionCompletion(Mission $mission, array $currentValues): bool
    {
        $missionValues = is_array($mission->values) ? $mission->values : json_decode($mission->values, true);
        $missionEvents = is_array($mission->events) ? $mission->events : json_decode($mission->events, true);

        // get the first event and its required value since we're using single event missions
        $event = $missionEvents[0];
        $requiredValue = $missionValues[0];

        // check if current value meets or exceeds required value
        return isset($currentValues[$event]) && $currentValues[$event] >= $requiredValue;
    }

    /**
     * Disburse reward to user
     */
    public function disburseReward(Mission $mission, User $user): void
    {
        if (empty($this->currentUser) || is_null($this->currentUser)) {
            // update current user when processing mission claim outside of misson service class possuble currentUser is empty
            $this->currentUser = $user;
        }

        if ($this->hasReachedRewardLimit($mission)) {
            Log::info('Mission reward limit reached', [
                'mission_id' => $mission->id,
                'user_id' => $user->id,
            ]);
            return;
        }

        DB::transaction(function () use ($mission, $user) {
            // create disbursement record
            $disbursement = MissionRewardDisbursement::create([
                'mission_id' => $mission->id,
                'user_id' => $user->id,
                'reward_quantity' => $mission->reward_quantity
            ]);

            // process reward based on type
            $this->processReward($mission, $user);

            // Update claimed status for manual claims
            if (!$mission->auto_disburse_rewards) {
                $this->updateClaimedStatus($mission, $user);
            }

            // Send reward notification
            $this->sendRewardReceivedNotification($mission, $user, $disbursement);
        });
    }

    /**
     * Process reward based on type
     */
    private function processReward(Mission $mission, User $user): void
    {
        $description = "Mission Completed - {$mission->name}";

        if ($mission->missionable_type === \App\Models\Reward::class) {
            $this->pointService->credit($mission, $user, $mission->reward_quantity, $description);
        } elseif ($mission->missionable_type === \App\Models\RewardComponent::class) {
            $this->pointComponentService->credit(
                $mission,
                $mission->missionable,
                $user,
                $mission->reward_quantity,
                $description
            );
        }
    }

    /**
     * Check if mission has reached reward limit
     */
    private function hasReachedRewardLimit(Mission $mission): bool
    {
        if ($mission->reward_limit <= 0) {
            return false;
        }

        $disbursedCount = MissionRewardDisbursement::where('mission_id', $mission->id)
            ->count();

        return $disbursedCount >= $mission->reward_limit;
    }

    /**
     * Update claimed status based on mission frequency
     */
    private function updateClaimedStatus(Mission $mission, User $user): void
    {
        $query = $user->missionsParticipating()
            ->where('mission_id', $mission->id)
            ->whereNull('claimed_at');

        switch ($mission->frequency) {
            case 'daily':
                $query->where('missions_users.created_at', '>=', now()->startOfDay());
                break;
            case 'monthly':
                $query->where('missions_users.created_at', '>=', now()->startOfMonth());
                break;
            case 'accumulated':
                $query->orderByDesc('missions_users.id')->limit(1);
                break;
        }

        $query->update(['claimed_at' => now()]);
    }

    /**
     * Send notifications with proper error handling
     */
    private function sendMissionStartedNotification(Mission $mission, User $user): void 
    {
        try {
            $locale = $user->last_lang ?? config('app.locale');
            $user->notify((new \App\Notifications\MissionStarted(
                $mission,
                $user,
                1,
                json_encode($mission->events)
            ))->locale($locale));
        } catch (\Exception $e) {
            Log::error('Failed to send mission started notification', [
                'mission_id' => $mission->id,
                'user_id' => $user->id,
                'error' => $e->getMessage()
            ]);
        }
    }

    private function sendMissionCompletedNotification(Mission $mission, User $user): void 
    {
        try {
            $user->notify(new MissionCompleted(
                $mission,
                $user,
                $mission->missionable->name,
                $mission->reward_quantity
            ));
        } catch (\Exception $e) {
            Log::error('Failed to send mission completed notification', [
                'mission_id' => $mission->id,
                'user_id' => $user->id,
                'error' => $e->getMessage()
            ]);
        }
    }

    private function sendRewardReceivedNotification(Mission $mission, User $user, MissionRewardDisbursement $disbursement): void
    {
        try {
            $this->currentUser->notify(new RewardReceivedNotification(
                $mission->missionable,
                $disbursement->reward_quantity,
                $this->currentUser,
                $mission->name,
                $mission
            ));
        } catch (\Exception $e) {
            Log::error('Failed to send reward received notification', [
                'mission_id' => $mission->id,
                'user_id' => $user->id,
                'disbursement_id' => $disbursement->id,
                'error' => $e->getMessage()
            ]);
        }
    }
    
    protected function arePredecessorsCompleted(User $user, Mission $mission): bool
    {
        // if no predecessors, return true
        if ($mission->predecessors->isEmpty()) {
            Log::info('No predecessors found', [
                'mission_id' => $mission->id,
                'user_id' => $user->id,
                'frequency' => $mission->frequency
            ]);
            return true;
        }

        // get all predecessors and their completion status
        $predecessorStatuses = $mission->predecessors()
            ->with(['participants' => function($query) use ($user) {
                $query->where('user_id', $user->id);
            }])
            ->get();

        Log::info('Predecessor statuses', [
            'mission_id' => $mission->id,
            'user_id' => $user->id,
            'predecessor_statuses' => $predecessorStatuses
        ]);

        // check if any predecessor is not completed
        foreach ($predecessorStatuses as $predecessor) {
            $isCompleted = $predecessor->participants
                ->contains(function($participation) {
                    return $participation->pivot->is_completed;
                });

            if (!$isCompleted) {
                Log::info('Predecessor not completed', [
                    'mission_id' => $mission->id,
                    'predecessor_id' => $predecessor->id,
                    'user_id' => $user->id,
                ]);
                return false;
            }
        }

        return true;
    }
}
