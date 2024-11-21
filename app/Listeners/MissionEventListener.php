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
use Illuminate\Support\Facades\Log;

class MissionEventListener
{
    protected MissionService $missionService;
    protected array $eventMatrix;

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
                'interaction_id' => $interaction->id
            ]);
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
        $this->missionService->handleEvent('comment_created', $event->comment->user);
    }

    protected function handleCommentLiked(CommentLiked $event): void
    {
        if ($event->liked) {
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
     * Check for spam interactions
     */
    protected function isSpamInteraction($user, $interaction): bool
    {
        $spamThreshold = now()->subMinutes(config('app.missions_spam_threshold', 1));

        return Interaction::where('user_id', $user->id)
            ->where('interactable_type', $interaction->interactable_type)
            ->where('interactable_id', $interaction->interactable_id)
            ->where('created_at', '>=', $spamThreshold)
            ->count() > 1;
    }

    /**
     * Check for spam following
     */
    protected function isSpamFollowing($user, $followedUser): bool
    {
        $spamThreshold = now()->subMinutes(config('app.missions_spam_threshold', 1));

        return $user->followings()
            ->where('following_id', $followedUser->id)
            ->where('users_followings.created_at', '>=', $spamThreshold)
            ->count() > 1;
    }
}
