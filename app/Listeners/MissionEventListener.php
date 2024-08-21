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
use App\Notifications\MissionCompleted;
use App\Notifications\RewardReceivedNotification;
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
        } else if ($event instanceof \App\Events\PurchasedMerchantOffer) {
            $this->handlePurchasedMerchantOffer($event);
        } else if ($event instanceof \App\Events\RatedStore) {
            $this->handleRatedStore($event);
        }
    }

    private function handlePurchasedMerchantOffer($event)
    {
        if ($event->paymentMethod == 'points') {
            $this->updateMissionProgress('purchased_merchant_offer_points', $event->user, 1);
        } else if ($event->paymentMethod == 'cash') {
            $this->updateMissionProgress('purchased_merchant_offer_cash', $event->user, 1);
        }
    }

    private function handleRatedStore($event)
    {
        $this->updateMissionProgress('reviewed_store', $event->user, 1);
    }

    /**
     * Handle Interactions (like, share, bookmark)
     */
    private function handleInteractionCreated($event)
    {
        $eventType = null;
        $user = $event->interaction->user;
        $interaction = $event->interaction;

        // Check if the user is spamming interactions
        if ($this->isSpamInteraction($user, $interaction)) {
            Log::warning('[MissionEventListener] User spam interaction detected', [
                'user' => $user->id,
                'interaction' => $interaction->id,
            ]);
            return;
        }
        if ($interaction->interactable_type == Article::class && $interaction->type == Interaction::TYPE_LIKE) {
            $eventType = 'like_article';
        } else if ($interaction->interactable_type == Comment::class && $interaction->type == Interaction::TYPE_LIKE) {
            $eventType = 'like_comment';
        } else if ($interaction->interactable_type == Article::class && $interaction->type == Interaction::TYPE_SHARE) {
            $eventType = 'share_article';
        } else if ($interaction->interactable_type == Article::class && $interaction->type == Interaction::TYPE_BOOKMARK) {
            $eventType = 'bookmark_an_article';
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
        if ($this->isSpamFollowing($event->user, $event->followedUser)) {
            Log::warning('[MissionEventListener] User spam following detected', [
                'user' => $event->user->id,
                'followed_user' => $event->followedUser->id,
            ]);
            return;
        }
        $this->updateMissionProgress('follow_a_user', $event->user, 1);
        $this->updateMissionProgress('accumulated_followers', $event->followedUser, 1);
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

        // double check if user has completed one-off mission, if yes then skip below
        $oneOffMissions = $user->missionsParticipating()->where('is_completed', true)
            ->whereIn('mission_id', $missions->pluck('id'))
            ->where('frequency', 'one-off')
            ->get();

        if ($oneOffMissions->count() > 0) {
            Log::info('User already completed one-off mission', [
                'user' => $user->id,
                'missions' => $missions->pluck('id')->toArray(),
                'latest_completions' => $oneOffMissions->map(function ($mission) {
                    return [
                        'mission_id' => $mission->mission_id,
                        'completed_at' => $mission->completed_at,
                    ];
                })->toArray(),
            ]);
            return;
        }

        foreach ($missions as $mission) {
            $userMission = $user->missionsParticipating()->where('is_completed', false)
                ->where('mission_id', $mission->id)
                ->orderBy('id', 'desc') // latest one first
                ->first();

            if (!$userMission) {
                // Check if user has already completed the mission within the current day or month
                if ($mission->frequency == 'daily') {
                    $completedToday = $user->missionsParticipating()
                        ->where('mission_id', $mission->id)
                        ->where('completed_at', '>=', now()->startOfDay())
                        ->where('completed_at', '<', now()->endOfDay())
                        ->exists();

                    if ($completedToday) {
                        continue; // Skip creating a new record if already completed today
                    }
                } elseif ($mission->frequency == 'monthly') {
                    $completedThisMonth = $user->missionsParticipating()
                        ->where('mission_id', $mission->id)
                        ->where('completed_at', '>=', now()->startOfMonth())
                        ->where('completed_at', '<', now()->endOfMonth())
                        ->exists();

                    if ($completedThisMonth) {
                        continue; // Skip creating a new record if already completed this month
                    }
                } elseif ($mission->frequency == 'accumulated') {
                    // check if user has completed similar mission before if not, skip
                    $completedMissions = $user->missionsParticipating()
                        ->where('mission_id', $mission->id)
                        ->whereNull('completed_at')
                        ->get();

                    if ($completedMissions->count() > 0) { // since user can only do accumulative mission once per time.
                        continue;
                    }
                }

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

                try {
                    $locale = $user->last_lang ?? config('app.locale');
                    $user->notify((new MissionCompleted($mission, $user, $mission->missionable->name, $mission->reward_quantity))->locale($locale));
                } catch (\Exception $e) {
                    Log::error('Mission Completed Notification Error', [
                        'mission_id' => $mission->id,
                        'user' => $user->id,
                        'error' => $e->getMessage()
                    ]);
                }

                if ($mission->auto_disburse_rewards) {
                    $this->disburseRewardsBasedOnFrequency($mission, $user);
                }
            }
        }
    }

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
        } elseif ($mission->frequency == 'accumulated') {
            if (!$lastRewardedAt) {
                $this->disburseRewards($mission, $user);
                $userMission->pivot->last_rewarded_at = now();
                $userMission->pivot->save();
            }
        }
    }

    /**
     * Check if Mission is Completed
     *
     * @param array $missionEvents
     * @param array $missionValues
     * @param array $currentValues
     * @return boolean
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
     * Disburse rewards to user
     *
     * @param Mission $mission
     * @param User $user
     * @return void
     */
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
        try {
            MissionRewardDisbursement::create([
                'mission_id' => $mission->id,
                'user_id' => $user->id,
                'reward_quantity' => $mission->reward_quantity
            ]);

            // here dont update claimed_at as claimed_at is for manual claiming /api/missions/complete
        } catch (\Exception $e) {
            Log::error('[MissionEventListener] Error disbursing reward for mission ' . $mission->id . ' and user ' . $user->id . ' error: ' . $e->getMessage());
        }

        $locale = $user->last_lang ?? config('app.locale');

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
    }

    /**
     * Check if user has spam interaction
     *
     * @param User $user
     * @param Interaction $interaction
     * @return boolean
     */
    private function isSpamInteraction($user, $interaction)
    {
        $spamThreshold = now()->subMinutes(config('app.missions_spam_threshold'));

        $recentInteractions = Interaction::where('user_id', $user->id)
            ->where('interactable_type', $interaction->interactable_type)
            ->where('interactable_id', $interaction->interactable_id)
            ->where('created_at', '>=', $spamThreshold)
            ->count();

        return $recentInteractions > 1;
    }

    /**
     * Check if user has spam following
     *
     * @param User $user
     * @param User $followedUser
     * @return boolean
     */
    private function isSpamFollowing($user, $followedUser)
    {
        $spamThreshold = now()->subMinutes(config('app.missions_spam_threshold'));

        $recentFollowings = $user->followings()
            ->where('following_id', $followedUser->id)
            ->where('users_followings.created_at', '>=', $spamThreshold)
            ->count();

        return $recentFollowings > 1;
    }
}
