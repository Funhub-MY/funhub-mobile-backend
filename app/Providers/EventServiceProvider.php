<?php

namespace App\Providers;

use App\Models\Approval;
use App\Events\ArticleCreated;
use App\Events\CommentCreated;
use App\Events\CompletedProfile;
use App\Events\FollowedUser;
use App\Listeners\MediaListener;
use App\Events\InteractionCreated;
use App\Events\MerchantOfferPublished;
use App\Events\PurchasedMerchantOffer;
use App\Events\RatedLocation;
use App\Events\RatedStore;
use App\Events\UserReferred;
use App\Events\UserSettingsUpdated;
use App\Observers\ApprovalObserver;
use App\Models\SupportRequestMessage;
use Illuminate\Support\Facades\Event;
use Illuminate\Auth\Events\Registered;
use App\Listeners\MissionEventListener;
use App\Listeners\SyncHashtagsToSearchKeywords;
use App\Listeners\CreateViewsForArticleListener;
use App\Listeners\MerchantOfferPublishedListener;
use App\Listeners\RatedLocationListener;
use App\Listeners\RecommendationAutoByPass;
use App\Listeners\UpdateLastRatedForMerchantOfferClaim;
use App\Listeners\UserReferredListener;
use App\Listeners\UserSettingsSavedListener;
use App\Models\ArticleTag;
use App\Models\MerchantOffer;
use App\Observers\ArticleTagObserver;
use App\Observers\SupportRequestMessageObserver;
use Illuminate\Auth\Listeners\SendEmailVerificationNotification;
use Spatie\MediaLibrary\MediaCollections\Events\MediaHasBeenAdded;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;

class EventServiceProvider extends ServiceProvider
{
    /**
     * The event to listener mappings for the application.
     *
     * @var array<class-string, array<int, class-string>>
     */
    protected $listen = [
        Registered::class => [
            SendEmailVerificationNotification::class,
        ],

        MediaHasBeenAdded::class => [
            MediaListener::class,
        ],

        InteractionCreated::class => [
            MissionEventListener::class,
        ],

        CommentCreated::class => [
            MissionEventListener::class,
        ],

        ArticleCreated::class => [
            MissionEventListener::class,
            CreateViewsForArticleListener::class,
            SyncHashtagsToSearchKeywords::class,
            RecommendationAutoByPass::class,
        ],

        UserReferred::class => [
            UserReferredListener::class, // for fixed referral reward (see config app REFERRAL_REWARD)
            MissionEventListener::class, // for user referred event
        ],

        UserSettingsUpdated::class => [
            UserSettingsSavedListener::class,
        ],

        CompletedProfile::class => [
            MissionEventListener::class,
        ],

        PurchasedMerchantOffer::class => [
            MissionEventListener::class,
        ],

        FollowedUser::class => [
            MissionEventListener::class,
        ],

        RatedLocation::class => [
            RatedLocationListener::class,
        ],

        MerchantOfferPublished::class => [
            MerchantOfferPublishedListener::class,
        ],

        RatedStore::class => [
            UpdateLastRatedForMerchantOfferClaim::class,
            MissionEventListener::class,
        ]
    ];

    /**
     * Register any events for your application.
     *
     * @return void
     */
    public function boot()
    {
        SupportRequestMessage::observe(SupportRequestMessageObserver::class);
        Approval::observe(ApprovalObserver::class);
        ArticleTag::observe(ArticleTagObserver::class);
    }

    /**
     * Determine if events and listeners should be automatically discovered.
     *
     * @return bool
     */
    public function shouldDiscoverEvents()
    {
        return false;
    }
}
