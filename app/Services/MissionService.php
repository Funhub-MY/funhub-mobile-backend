<?php

namespace App\Services;

use App\Models\Mission;
use App\Models\MissionRewardDisbursement;
use App\Models\User;
use App\Events\MissionProgressUpdated;
use App\Notifications\MissionCompleted;
use App\Notifications\RewardReceivedNotification;
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
        $latestParticipation = $mission->participants()
            ->where('user_id', $user->id)
            ->orderByDesc('missions_users.created_at')
            ->first();

        if (!$latestParticipation) {
            return true;
        }

        // convert completed_at to carbon if it exists
        $completedAt = $latestParticipation->pivot->completed_at
            ? \Carbon\Carbon::parse($latestParticipation->pivot->completed_at)
            : null;

        return match ($mission->frequency) {
            'one-off' => !$latestParticipation->pivot->is_completed,
            'daily' => !$completedAt || !$completedAt->isToday(),
            'accumulated' => !$latestParticipation->pivot->is_completed || $latestParticipation->pivot->claimed_at,
            'monthly' => !$completedAt || !$completedAt->isSameMonth(now()),
            default => true,
        };
    }

    /**
     * Process mission progress
     */
    private function processMissionProgress(Mission $mission, User $user, string $eventType): void
    {
        $userMission = $this->getOrCreateUserMission($mission, $user, $eventType);

        if (!$userMission->pivot->is_completed) {
            $this->updateProgress($userMission, $mission, $eventType);
        }
    }

    /**
     * Get existing or create new user mission progress
     */
    private function getOrCreateUserMission(Mission $mission, User $user, string $eventType)
    {
        $userMission = $user->missionsParticipating()
            ->where('mission_id', $mission->id)
            ->where('is_completed', false)
            ->orderByDesc('missions_users.id')
            ->first();

        if (!$userMission) {
            $currentValues = collect($mission->events)
                ->mapWithKeys(fn ($event) => [$event => 0])
                ->toArray();

            $user->missionsParticipating()->attach($mission->id, [
                'started_at' => now(),
                'current_values' => json_encode($currentValues)
            ]);

            $userMission = $user->missionsParticipating()
                ->where('mission_id', $mission->id)
                ->orderByDesc('missions_users.id')
                ->first();

            $this->sendMissionStartedNotification($mission, $user);
        }

        return $userMission;
    }

    /**
     * Update mission progress and check completion
     */
    private function updateProgress($userMission, Mission $mission, string $eventType): void
    {
        $currentValues = json_decode($userMission->pivot->current_values, true);
        $currentValues[$eventType] = ($currentValues[$eventType] ?? 0) + 1;

        $userMission->pivot->current_values = json_encode($currentValues);
        $userMission->pivot->save();

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
