<?php

namespace App\Listeners;

use App\Models\Article;
use App\Models\Comment;
use App\Models\Mission;
use App\Models\Interaction;
use App\Events\FollowedUser;
use App\Events\ArticleCreated;
use App\Events\CommentCreated;
use App\Events\CommentLiked;
use App\Services\MissionService;
use App\Events\InteractionCreated;
use App\Models\Store;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class MissionEventListener
{
    protected MissionService $missionService;
    protected array $eventMatrix;

    // spam detection thresholds
    const INTERACTION_SPAM_LIMIT = 3;
    const INTERACTION_SPAM_WINDOW = 10; // minutes
    
    const FOLLOW_SPAM_LIMIT = 2;
    const FOLLOW_SPAM_WINDOW = 10; // minutes
    
    const COMMENT_SPAM_LIMIT = 5;
    const COMMENT_SPAM_WINDOW = 10; // minutes
    
    const COMMENT_COOLDOWN = 30; // seconds

    public function __construct(MissionService $missionService)
    {
        $this->missionService = $missionService;
        $this->eventMatrix = config('app.event_matrix');
    }

    /**
     * Handle mission-related events
     */
    public function handle($event): void
    {
        try {
            match (true) {
                $event instanceof InteractionCreated => $this->handleInteractionCreated($event),
                $event instanceof CommentCreated => $this->handleCommentCreated($event),
                $event instanceof CommentLiked => $this->handleCommentLiked($event),
                $event instanceof ArticleCreated => $this->handleArticleCreated($event),
                $event instanceof FollowedUser => $this->handleFollowings($event),
                $event instanceof \App\Events\CompletedProfile => $this->handleProfileCompleted($event),
                $event instanceof \App\Events\PurchasedMerchantOffer => $this->handlePurchasedMerchantOffer($event),
                $event instanceof \App\Events\RatedStore => $this->handleRatedStore($event),
                $event instanceof \App\Events\ClosedSupportTicket => $this->handleClosedSupportTicket($event),
                default => null,
            };
        } catch (\Exception $e) {
            Log::error('Error handling mission event', [
                'event' => get_class($event),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }
    }

    /**
     * Handle interaction events (like, share, bookmark)
     */
    protected function handleInteractionCreated(InteractionCreated $event): void
    {
        $interaction = $event->interaction;
        $user = $interaction->user;

        if ($this->isSpamInteraction($user, $interaction)) {
            Log::warning('Spam interaction detected', [
                'user_id' => $user->id,
                'interaction_id' => $interaction->id,
                'type' => $interaction->type,
                'interactable_type' => $interaction->interactable_type,
                'interactable_id' => $interaction->interactable_id
            ]);
            return;
        }

        if ($this->isOwnArticleInteraction($event)) {
            return;
        }

        $eventType = $this->mapInteractionToEventType($interaction);
        if ($eventType) {
            $this->missionService->handleEvent($eventType, $user, ['interaction' => $interaction]);
        }
    }

    /**
     * Map interaction to event type
     */
    protected function mapInteractionToEventType(Interaction $interaction): ?string
    {
        return match (true) {
            $interaction->interactable_type === Article::class && $interaction->type === Interaction::TYPE_LIKE => 'like_article',
            $interaction->interactable_type === Comment::class && $interaction->type === Interaction::TYPE_LIKE => 'like_comment',
            $interaction->interactable_type === Article::class && $interaction->type === Interaction::TYPE_SHARE => 'share_article',
            $interaction->interactable_type === Article::class && $interaction->type === Interaction::TYPE_BOOKMARK => 'bookmark_an_article',
            default => null,
        };
    }

    protected function handleCommentCreated(CommentCreated $event): void
    {
        if ($this->isSpamComment($event->comment->user, $event->comment)) {
            Log::warning('Spam comment detected', [
                'user_id' => $event->comment->user->id,
                'comment_id' => $event->comment->id
            ]);
            return;
        }

        if ($this->isOwnArticleInteraction($event)) {
            return;
        }

        $this->missionService->handleEvent('comment_created', $event->comment->user);
    }

    protected function handleCommentLiked(CommentLiked $event): void
    {
        if ($event->liked && !$this->isOwnArticleInteraction($event)) {
            $this->missionService->handleEvent('like_comment', $event->user);
        }
    }

    protected function handleArticleCreated(ArticleCreated $event): void
    {
        $this->missionService->handleEvent('article_created', $event->article->user);
    }

    protected function handleFollowings(FollowedUser $event): void
    {
        if ($this->isSpamFollowing($event->user, $event->followedUser)) {
            Log::warning('Spam following detected', [
                'user_id' => $event->user->id,
                'followed_user_id' => $event->followedUser->id
            ]);
            return;
        }

        $this->missionService->handleEvent('follow_a_user', $event->user, [
            'followed_user' => $event->followedUser
        ]);

        $this->missionService->handleEvent('accumulated_followers', $event->followedUser);
    }

    protected function handleProfileCompleted(\App\Events\CompletedProfile $event): void
    {
        $this->missionService->handleEvent('completed_profile_setup', $event->user);
    }

    protected function handlePurchasedMerchantOffer(\App\Events\PurchasedMerchantOffer $event): void
    {
        $eventType = $event->paymentMethod === 'points'
            ? 'purchased_merchant_offer_points'
            : 'purchased_merchant_offer_cash';

        $this->missionService->handleEvent($eventType, $event->user);
    }

    protected function handleRatedStore(\App\Events\RatedStore $event): void
    {
        if ($this->isSpamRating($event->user, $event->store)) {
            Log::warning('Spam rating detected', [
                'user_id' => $event->user->id,
                'store_id' => $event->store->id
            ]);
            return;
        }

        $this->missionService->handleEvent('reviewed_store', $event->user);
    }

    protected function handleClosedSupportTicket(\App\Events\ClosedSupportTicket $event): void
    {
        $supportRequest = $event->supportRequest;
        $supportType = $supportRequest->category->type;

        if (!in_array($supportType, ['complain', 'information_update'])) {
            Log::info('Support request not eligible for mission', [
                'request_id' => $supportRequest->id,
                'type' => $supportType
            ]);
            return;
        }

        $closedAudits = $supportRequest->audits()
            ->where('new_values->status', $supportRequest::STATUS_CLOSED)
            ->where('old_values->status', '!=', $supportRequest::STATUS_CLOSED)
            ->count();

        if ($closedAudits > 1) {
            Log::info('Support request already closed before', [
                'request_id' => $supportRequest->id
            ]);
            return;
        }

        $eventType = $supportType === 'complain'
            ? 'closed_a_ticket'
            : 'closed_an_information_update_ticket';

        $this->missionService->handleEvent($eventType, $event->supportRequest->requestor);
    }

    /**
     * Check for spam interactions (e.g., repeated likes/unlikes on the same content)
     */
    protected function isSpamInteraction(User $user, Interaction $interaction): bool
    {
        $cacheKey = "spam_interaction:{$user->id}:{$interaction->interactable_type}:{$interaction->interactable_id}:{$interaction->type}";
        
        // check if user has recently interacted with this content
        if (Cache::has($cacheKey)) {
            return true;
        }

        // set a cooldown period for this interaction
        Cache::put($cacheKey, true, now()->addMinutes(config('app.missions_spam_threshold', 10)));

        // also check database for historical spam patterns
        $spamThreshold = now()->subMinutes(self::INTERACTION_SPAM_WINDOW);
        
        return Interaction::where('user_id', $user->id)
            ->where('interactable_type', $interaction->interactable_type)
            ->where('interactable_id', $interaction->interactable_id)
            ->where('type', $interaction->type)
            ->where('created_at', '>=', $spamThreshold)
            ->count() > self::INTERACTION_SPAM_LIMIT;
    }

    /**
     * Check for spam follow/unfollow actions
     */
    protected function isSpamFollowing(User $user, User $followedUser): bool
    {
        $cacheKey = "spam_following:{$user->id}:{$followedUser->id}";
        
        if (Cache::has($cacheKey)) {
            return true;
        }

        Cache::put($cacheKey, true, now()->addMinutes(config('app.missions_spam_threshold', 10)));

        $spamThreshold = now()->subMinutes(self::FOLLOW_SPAM_WINDOW);
        
        return DB::table('users_followings')
            ->where('user_id', $user->id)
            ->where('following_id', $followedUser->id)
            ->where('created_at', '>=', $spamThreshold)
            ->count() > self::FOLLOW_SPAM_LIMIT;
    }

    /**
     * Check for spam comments
     */
    protected function isSpamComment(User $user, Comment $comment): bool
    {
        $cacheKey = "spam_comment:{$user->id}:{$comment->commentable_type}:{$comment->commentable_id}";
        
        if (Cache::has($cacheKey)) {
            return true;
        }

        Cache::put($cacheKey, true, now()->addSeconds(self::COMMENT_COOLDOWN));

        $spamThreshold = now()->subMinutes(self::COMMENT_SPAM_WINDOW);
        
        return Comment::where('user_id', $user->id)
            ->where('created_at', '>=', $spamThreshold)
            ->count() > self::COMMENT_SPAM_LIMIT;
    }

    /**
     * Check if user has already rated this store before (to prevent mission progress abuse)
     * Returns false for new ratings, true for repeated ratings
     */
    protected function isSpamRating(User $user, Store $store): bool
    {
        // Check if user has rated this store before (even if rating was updated)
        // If exists() is false, it means it's a new rating (not spam)
        return \App\Models\StoreRating::where('user_id', $user->id)
            ->where('store_id', $store->id)
            ->where('created_at', '<', now()) // Only check for ratings created before this one
            ->exists();
    }

    /**
     * Check if interaction/comment is on user's own article or own comment
     */
    protected function isOwnArticleInteraction($event): bool
    {
        if ($event instanceof InteractionCreated) {
            $interactable = $event->interaction->interactable;
            if ($interactable instanceof Article) {
                return $interactable->user_id === $event->interaction->user_id;
            }
        } elseif ($event instanceof CommentCreated) {
            $commentable = $event->comment->commentable;
            if ($commentable instanceof Article) {
                return $commentable->user_id === $event->comment->user_id;
            }
        } elseif ($event instanceof CommentLiked && $event->liked) {
            return $event->comment->user_id === $event->user->id;
        }
        return false;
    }
}
