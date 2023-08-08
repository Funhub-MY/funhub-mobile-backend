<?php

namespace App\Listeners;

use App\Models\Article;
use App\Models\Comment;
use App\Models\Mission;
use App\Models\Interaction;
use App\Events\FollowedUser;
use Intervention\Image\Point;
use App\Events\ArticleCreated;
use App\Events\CommentCreated;
use App\Events\UnfollowedUser;
use App\Services\PointService;
use App\Events\InteractionCreated;
use App\Models\Reward;
use App\Models\RewardComponent;
use Illuminate\Support\Facades\Log;
use App\Services\PointComponentService;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;

class MissionEventListener
{
    private $eventMatrix, $pointService, $pointComponentService;
    /**
     * Create the event listener.
     *
     * @return void
     */
    public function __construct(PointService $pointService, PointComponentService $pointComponentService)
    {
        $this->eventMatrix = config('app.event_matrix');
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
            $this->handleFollowings($event, 'follow');
        } else if ($event instanceof UnfollowedUser) {
            $this->handleFollowings($event, 'unfollow');
        }
    }

    /**
     * Handle Interactions (like, share, bookmark)
     */
    private function handleInteractionCreated($event)
    {
        // check event type   
        $eventType = null;
        $user = $event->interaction->user;
        $interaction = $event->interaction;
        if ($interaction->interactable_type == Article::class && $interaction->type == Interaction::TYPE_LIKE) {
            // likes an article
            $eventType = 'like_article';
        } else if ($interaction->interactable_type == Comment::class && $interaction->type == Interaction::TYPE_LIKE) {
            // like a comment
            $eventType = 'like_comment';
        } else if ($interaction->interactable_type == Article::class && $interaction->type == Interaction::TYPE_SHARE) {
            // share an article
            $eventType = 'share_article';
        } else if ($interaction->interactable_type == Article::class && $interaction->type == Interaction::TYPE_BOOKMARK) {
            // bookmark an article
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
    private function handleFollowings($event, $type)
    {
        if ($type == 'follow') {
            $this->updateMissionProgress('follow_user', $event->user, 1);
        } else if ($type == 'unfollow') {
            $this->revertMissionProgress('follow_user', $event->user, 1);
        }
    }

    /**
     * Update Mission Progress
     */
    private function updateMissionProgress($eventType, $user, $increments)
    {
        $mission = Mission::where('event', $eventType)->where('enabled', true)->first();
        if (!$mission) return;

        $userMission = $user->missionsParticipating()->where('is_completed', false)
            ->where('mission_id', $mission->id)
            ->first();
        
        if (!$userMission) {
            $user->missionsParticipating()->attach($mission->id, [
                'started_at' => now(),
                'current_value' => $increments
            ]);
            if ($increments == $mission->value) $this->disburseRewards($mission, $user);
        } else if (!$userMission->pivot->is_completed) {

            // increment pivot->current_value
            $userMission->pivot->increment('current_value', $increments);

            if ($userMission->pivot->current_value >= $mission->value) {
                // $userMission->pivot->is_completed = true;
                $this->disburseRewards($mission, $user);
            }
        }
    }

    /**
     * Revert Mission Progress
     */
    private function revertMissionProgress($eventType, $user, $decrements) 
    {
        $mission = Mission::where('event', $eventType)
            ->where('enabled', true)->first();
        if (!$mission) return;

        // ensure user has participated in the mission and has not yet complete it
        $userMission = $user->missionsParticipating()->where('is_completed', false)->where('mission_id', $mission->id)->first();
        if (!$userMission) return;

        // decrements only if the current value > 0
        if ($userMission->pivot->current_value > 0) {
            $userMission->pivot->decrement('current_value', $decrements);
        }
    }

    /**
     * Disburse Rewards
     */
    private function disburseRewards($mission, $user)
    {
        // only auto disburse if system set to auto disburse true
        if (config('app.auto_disburse_reward')) {
             // depending on mission->missionable_type to reward
            $missionableType = $mission->missionable_type;
            $missionableId = $mission->missionable_id;

            if ($missionableType == Reward::class) {
                // reward point via pointService
                $this->pointService->credit($mission, $user, $mission->reward_quantity, 'Mission Completed - '. $mission->name);
                Log::info('Mission Completed and Disbursed Reward', [
                    'mission' => $mission->id,
                    'user' => $user->id,
                    'reward_type' => 'point',
                    'reward' => $mission->reward_quantity
                ]);
            } else if ($missionableType == RewardComponent::class) {
                // reward point via pointComponentService
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
