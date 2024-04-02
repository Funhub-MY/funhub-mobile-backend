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
use App\Models\Reward;
use App\Models\RewardComponent;
use Illuminate\Support\Facades\Log;
use App\Services\PointComponentService;

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
        $missions = Mission::where('enabled', true)
            ->whereJsonContains('events', $eventType)
            ->get();

        foreach ($missions as $mission) {
            $userMission = $user->missionsParticipating()->where('is_completed', false)
                ->where('mission_id', $mission->id)
                ->first();

            if (!$userMission) {
                $currentValues = [];
                foreach ($mission->events as $event) {
                    $currentValues[$event] = ($event == $eventType) ? $increments : 0;
                }
                $user->missionsParticipating()->attach($mission->id, [
                    'started_at' => now(),
                    'current_values' => json_encode($currentValues)
                ]);
            } else if (!$userMission->pivot->is_completed) {
                $currentValues = json_decode($userMission->pivot->current_values, true);
                $currentValues[$eventType] = isset($currentValues[$eventType]) ? $currentValues[$eventType] + $increments : $increments;
                $userMission->pivot->current_values = json_encode($currentValues);
                $userMission->pivot->save();
            }

            if ($this->isMissionCompleted($mission->values, $currentValues)) {
                $this->disburseRewardsBasedOnFrequency($mission, $user);
            }
        }
    }
    /**
     * Check if Mission is Completed
     */
    private function isMissionCompleted($missionValues, $currentValues)
    {
        foreach ($missionValues as $event => $value) {
            if (!isset($currentValues[$event]) || $currentValues[$event] < $value) {
                return false;
            }
        }
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

    /**
     * Disburse Rewards
     */
    private function disburseRewards($mission, $user)
    {
        if (config('app.auto_disburse_reward')) {
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
                $this->pointComponentService->credit($mission, $missionableType, $user, $mission->reward_quantity, 'Mission Completed - '. $mission->name);
                Log::info('Mission Completed and Disbursed Reward', [
                    'mission' => $mission->id,
                    'user' => $user->id,
                    'reward_type' => 'point component',
                    'reward' => $mission->reward_quantity
                ]);
            }
        }
    }
}
