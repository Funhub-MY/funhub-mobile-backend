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

    public function __construct(
        PointService $pointService,
        PointComponentService $pointComponentService
    ) {
        $this->pointService = $pointService;
        $this->pointComponentService = $pointComponentService;
    }

    /**
     * Handle mission progress for an event
     */
    public function handleEvent(string $eventType, User $user, array $context = []): void
    {
        try {
            DB::beginTransaction();

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
            ]);
            throw $e;
        }
    }

    /**
     * Get eligible missions for the event
     */
    private function getEligibleMissions(string $eventType, User $user): \Illuminate\Database\Eloquent\Collection
    {
        return Mission::enabled()
            ->with(['participants' => function ($query) use ($user) {
                $query->where('user_id', $user->id);
            }])
            ->whereJsonContains('events', $eventType)
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
        Log::info('Checking mission eligibility', [
            'mission_id' => $mission->id,
            'frequency' => $mission->frequency
        ]);

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
            'daily' => ($startedAt && $startedAt->isToday() && !$latestParticipation->pivot->is_completed) || !$startedAt,
            'accumulated' => !$latestParticipation->pivot->is_completed || $latestParticipation->pivot->claimed_at,
            'monthly' => !$latestParticipation->pivot->completed_at || !Carbon::parse($latestParticipation->pivot->completed_at)->isSameMonth(now()),
            default => true,
        };

        Log::info('Mission eligibility check result', [
            'mission_id' => $mission->id,
            'frequency' => $mission->frequency,
            'is_eligible' => $isEligible,
            'started_at' => $startedAt ? $startedAt->toDateTimeString() : null,
            'is_completed' => $latestParticipation->pivot->is_completed,
        ]);

        return $isEligible;
    }

    /**
     * Process mission progress
     */
    private function processMissionProgress(Mission $mission, User $user, string $eventType): void
    {
        Log::info('Processing mission progress', [
            'mission_id' => $mission->id,
            'user_id' => $user->id,
            'event_type' => $eventType
        ]);

        $userMission = $this->getOrCreateUserMission($mission, $user, $eventType);

        if (!$userMission->pivot->is_completed) {
            $this->updateProgress($userMission, $mission, $eventType);
        } else {
            Log::info('Mission already completed', [
                'mission_id' => $mission->id,
                'user_id' => $user->id,
            ]);
        }
    }

    /**
     * Get existing or create new user mission progress
     */
    private function getOrCreateUserMission(Mission $mission, User $user, string $eventType)
    {
        $query = $user->missionsParticipating()
            ->where('mission_id', $mission->id)
            ->where('is_completed', false);

        if ($mission->frequency === 'daily') {
            $query->where('started_at', '>=', now()->startOfDay());
        }

        $userMission = $query->latest('id')->first();

        if (!$userMission) {
            DB::transaction(function() use ($mission, $user, $eventType) {
                $currentValues = collect($mission->events)
                    ->mapWithKeys(fn ($event) => [$event => 0])
                    ->toArray();

                $user->missionsParticipating()->attach($mission->id, [
                    'started_at' => now(),
                    'current_values' => json_encode($currentValues),
                    'is_completed' => false,
                    'completed_at' => null
                ]);

                Log::info('Created new mission progress', [
                    'mission_id' => $mission->id,
                    'user_id' => $user->id,
                    'current_values' => $currentValues
                ]);
            });

            $userMission = $user->missionsParticipating()
                ->where('mission_id', $mission->id)
                ->latest('id')
                ->first();
        } else {
            Log::info('Has existing mission progress', [
                'mission_id' => $mission->id,
                'user_id' => $user->id,
            ]);
        }

        return $userMission;
    }

    /**
     * Update mission progress and check completion
     */
    private function updateProgress($userMission, Mission $mission, string $eventType): void
    {
        Log::info('Starting progress update', [
            'mission_id' => $mission->id,
            'event_type' => $eventType,
            'before_values' => $userMission->pivot->current_values
        ]);

        $currentValues = json_decode($userMission->pivot->current_values, true);
        $oldCount = $currentValues[$eventType] ?? 0;
        $currentValues[$eventType] = $oldCount + 1;

        $userMission->pivot->current_values = json_encode($currentValues);
        $userMission->pivot->save();

        Log::info('Progress updated', [
            'mission_id' => $mission->id,
            'event_type' => $eventType,
            'old_count' => $oldCount,
            'new_count' => $currentValues[$eventType],
            'pivot_id' => $userMission->pivot->id
        ]);

        if ($this->checkMissionCompletion($mission, $currentValues)) {
            $this->handleMissionCompletion($mission, $userMission);
        }
    }

    /**
     * Check if mission is completed based on current values
     */
    private function checkMissionCompletion(Mission $mission, array $currentValues): bool
    {
        $missionValues = is_array($mission->values) ? $mission->values : json_decode($mission->values, true);
        $missionEvents = is_array($mission->events) ? $mission->events : json_decode($mission->events, true);

        $totalValue = 0;
        foreach ($missionEvents as $index => $event) {
            if (!isset($currentValues[$event])) {
                return false;
            }
            $totalValue = $currentValues[$event];
        }

        return $totalValue >= $missionValues[0];
    }

    /**
     * Handle mission completion and rewards
     */
    private function handleMissionCompletion($mission, $userMission): void
    {
        DB::transaction(function () use ($mission, $userMission) {
            // Update completion status
            $userMission->pivot->update([
                'is_completed' => true,
                'completed_at' => now()
            ]);

            // Send mission completed notification
            $this->sendMissionCompletedNotification($mission, $userMission->user);

            // Handle auto-disbursement if enabled
            if ($mission->auto_disburse_rewards) {
                $this->disburseReward($mission, $userMission->user);
            }
        });
    }

    /**
     * Disburse reward to user
     */
    public function disburseReward(Mission $mission, User $user): void
    {
        if ($this->hasReachedRewardLimit($mission)) {
            Log::info('Mission reward limit reached', [
                'mission_id' => $mission->id,
                'user_id' => $user->id,
            ]);
            return;
        }

        DB::transaction(function () use ($mission, $user) {
            // Create disbursement record
            $disbursement = MissionRewardDisbursement::create([
                'mission_id' => $mission->id,
                'user_id' => $user->id,
                'reward_quantity' => $mission->reward_quantity
            ]);

            // Process reward based on type
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
            $user->notify(new RewardReceivedNotification(
                $mission->missionable,
                $disbursement->reward_quantity,
                $user,
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
}
