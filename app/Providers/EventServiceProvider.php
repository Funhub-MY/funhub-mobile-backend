<?php

namespace App\Providers;

use App\Models\Approval;
use App\Events\ArticleCreated;
use App\Events\CommentCreated;
use App\Listeners\MediaListener;
use App\Events\InteractionCreated;
use App\Observers\ApprovalObserver;
use App\Models\SupportRequestMessage;
use Illuminate\Support\Facades\Event;
use Illuminate\Auth\Events\Registered;
use App\Listeners\MissionEventListener;
use App\Listeners\SyncHashtagsToSearchKeywords;
use App\Listeners\CreateViewsForArticleListener;
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
        ],
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
