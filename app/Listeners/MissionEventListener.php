<?php

namespace App\Listeners;

use App\Models\Article;
use App\Models\Comment;
use App\Models\Mission;
use App\Models\Interaction;
use App\Events\FollowedUser;
use App\Events\ArticleCreated;
use App\Events\CommentCreated;
use App\Services\PointService;
use App\Events\InteractionCreated;
use App\Models\MissionRewardDisbursement;
use App\Models\Reward;
use App\Models\RewardComponent;
use Illuminate\Support\Facades\Log;
use App\Services\PointComponentService;
use Illuminate\Support\Facades\DB;

class MissionEventListener
{
    private $pointService, $pointComponentService;

    /**
     * Create the event listener.
     *
     * @return void
     */
    public function __construct(PointService $pointService, PointComponentService $pointComponentService)
    {
        $this->pointService = $pointService;
        $this->pointComponentService = $pointComponentService;
    }

    /**
     * Handle the event.
     *
     * @param  object  $event
     * @return void
     */
    public function handle($event)
    {
        if ($event instanceof InteractionCreated) {
            $this->handleInteractionCreated($event);
        } else if ($event instanceof CommentCreated) {
            $this->handleCommentCreated($event);
        } else if ($event instanceof ArticleCreated) {
            $this->handleArticleCreated($event);
        } else if ($event instanceof FollowedUser) {
            $this->handleFollowings($event);
        } else if ($event instanceof \App\Events\CompletedProfile) {
            $this->updateMissionProgress('completed_profile_setup', $event->user, 1);
        }
    }

    /**
     * Handle Interactions (like, share, bookmark)
     */
    private function handleInteractionCreated($event)
    {
        $eventType = null;
        $user = $event->interaction->user;
        $interaction = $event->interaction;
        if ($interaction->interactable_type == Article::class && $interaction->type == Interaction::TYPE_LIKE) {
            $eventType = 'like_article';
        } else if ($interaction->interactable_type == Comment::class && $interaction->type == Interaction::TYPE_LIKE) {
            $eventType = 'like_comment';
        } else if ($interaction->interactable_type == Article::class && $interaction->type == Interaction::TYPE_SHARE) {
            $eventType = 'share_article';
        } else if ($interaction->interactable_type == Article::class && $interaction->type == Interaction::TYPE_BOOKMARK) {
            $eventType = 'bookmark_article';
        }

        $this->updateMissionProgress($eventType, $user, 1);
    }

    /**
     * Handle Comment Created
     */
    private function handleCommentCreated($event)
    {
        $comment = $event->comment;
        $user = $comment->user;
        $this->updateMissionProgress('comment_created', $user, 1);
    }

    /**
     * Handle Article Created
     */
    private function handleArticleCreated($event)
    {
        $article = $event->article;
        $user = $article->user;
        $this->updateMissionProgress('article_created', $user, 1);
    }

    /**
     * Handle Followings
     */
    private function handleFollowings($event)
    {
        $this->updateMissionProgress('follow_user', $event->user, 1);
    }

    /**
     * Update Mission Progress
     */
    private function updateMissionProgress($eventType, $user, $increments)
    {
        $missions = Mission::where('status', 1)->get();
        // filter by misisons->events
        $missions = $missions->filter(function ($mission) use ($eventType) {
            // if mission event is string decode, if not return as is
            $mission->events = is_string($mission->events) ? json_decode($mission->events) : $mission->events;
            return in_array($eventType, $mission->events);
        });

        foreach ($missions as $mission) {
            $userMission = $user->missionsParticipating()->where('is_completed', false)
                ->where('mission_id', $mission->id)
                ->first();

            if (!$userMission) {
                $currentValues = [];
                $mission->events = is_string($mission->events) ? json_decode($mission->events) : $mission->events;

                foreach ($mission->events as $event) {
                    $currentValues[$event] = ($event == $eventType) ? $increments : 0;
                }
                $user->missionsParticipating()->attach($mission->id, [
                    'started_at' => now(),
                    'current_values' => json_encode($currentValues)
                ]);

            } else if (!$userMission->pivot->is_completed) {
                // if current_value is string, decode it first
                $currentValues = is_string($userMission->pivot->current_values) ? json_decode($userMission->pivot->current_values, true) : $userMission->pivot->current_values;

                $currentValues[$eventType] = isset($currentValues[$eventType]) ? $currentValues[$eventType] + $increments : $increments;
                $userMission->pivot->current_values = json_encode($currentValues);
                $userMission->pivot->save();
            }

            if ($this->isMissionCompleted($mission->events, $mission->values, $currentValues)) {
                Log::info('Mission Completed, auto disburse rewards', [
                    'mission' => $mission->id,
                    'user' => $user->id
                ]);

                // update mission to completed first
                $user->missionsParticipating()->updateExistingPivot($mission->id, [
                    'is_completed' => true,
                    'completed_at' => now()
                ]);

                if ($mission->auto_disburse_rewards) {
                    $this->disburseRewardsBasedOnFrequency($mission, $user);
                }
            }
        }
    }
    /**
     * Check if Mission is Completed
     */
    private function isMissionCompleted($missionEvents, $missionValues, $currentValues)
    {
        // if missionvalues provided in string(json) decode first
        if (is_string($missionValues)) {
            $missionValues = json_decode($missionValues, true);
        }

        if (count($missionEvents) != count($missionValues)) {
            return false;
        }

        // mission events decode if string
        if (is_string($missionEvents)) {
            $missionEvents = json_decode($missionEvents, true);
        }

        // mission events = ['like_comment', 'like_article', 'commented']
        // mission values = [10, 20, 30]

        foreach ($missionValues as $index => $value) {
            if (!isset($currentValues[$missionEvents[$index]])) {
                return false;
            }

            // if current values like_comment => 10 < 10
            if ($currentValues[$missionEvents[$index]] < $value) {
                return false;
            }
        }

        Log::info('Mission Completed', [
            'events' => $missionEvents,
            'values' => $missionValues,
            'current' => $currentValues
        ]);
        return true;
    }

    /**
     * Disburse Rewards Based on Frequency
     */
    private function disburseRewardsBasedOnFrequency($mission, $user)
    {
        $userMission = $user->missionsParticipating()->where('mission_id', $mission->id)->first();
        $lastRewardedAt = $userMission->pivot->last_rewarded_at;

        if ($mission->frequency == 'one-off' && !$lastRewardedAt) {
            $this->disburseRewards($mission, $user);
            $userMission->pivot->last_rewarded_at = now();
            $userMission->pivot->save();
        } elseif ($mission->frequency == 'daily') {
            $currentDate = now()->startOfDay();
            if (!$lastRewardedAt || $lastRewardedAt->lt($currentDate)) {
                $this->disburseRewards($mission, $user);
                $userMission->pivot->last_rewarded_at = $currentDate;
                $userMission->pivot->save();
            }
        } elseif ($mission->frequency == 'monthly') {
            $currentMonth = now()->startOfMonth();
            if (!$lastRewardedAt || $lastRewardedAt->lt($currentMonth)) {
                $this->disburseRewards($mission, $user);
                $userMission->pivot->last_rewarded_at = $currentMonth;
                $userMission->pivot->save();
            }
        }
    }

    private function disburseRewards($mission, $user)
    {
        $disbursedRewardCount = MissionRewardDisbursement::where('mission_id', $mission->id)->sum('reward_quantity');

        if ($mission->reward_limit > 0 && $disbursedRewardCount >= $mission->reward_limit) {
            Log::info('Mission reward limit reached', [
                'mission' => $mission->id,
                'user' => $user->id,
                'reward_limit' => $mission->reward_limit
            ]);
            return;
        }

        $missionableType = $mission->missionable_type;
        $missionableId = $mission->missionable_id;

        if ($missionableType == Reward::class) {
            $this->pointService->credit($mission, $user, $mission->reward_quantity, 'Mission Completed - '. $mission->name);
            Log::info('Mission Completed and Disbursed Reward', [
                'mission' => $mission->id,
                'user' => $user->id,
                'reward_type' => 'point',
                'reward' => $mission->reward_quantity
            ]);
        } else if ($missionableType == RewardComponent::class) {
            $missionable = RewardComponent::find($missionableId); // RewardComponent
            $this->pointComponentService->credit($mission, $missionable, $user, $mission->reward_quantity, 'Mission Completed - '. $mission->name);
            Log::info('Mission Completed and Disbursed Reward', [
                'mission' => $mission->id,
                'user' => $user->id,
                'reward_type' => 'point component',
                'reward' => $mission->reward_quantity
            ]);
        }

        MissionRewardDisbursement::create([
            'mission_id' => $mission->id,
            'user_id' => $user->id,
            'reward_quantity' => $mission->reward_quantity
        ]);

        // fire notification to user
        $user->notify(new \App\Notifications\RewardReceivedNotification(
            $mission->missionable,
            $mission->reward_quantity,
            $user,
            $mission->name
        ));
    }
}
