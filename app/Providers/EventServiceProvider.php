<?php

namespace App\Providers;

use App\Events\ArticleCreated;
use App\Events\CommentCreated;
use App\Events\InteractionCreated;
use App\Listeners\MediaListener;
use App\Listeners\MissionEventListener;
use Illuminate\Auth\Events\Registered;
use Illuminate\Auth\Listeners\SendEmailVerificationNotification;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Event;
use Spatie\MediaLibrary\MediaCollections\Events\MediaHasBeenAdded;

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

        // InteractionCreated::class => [
        //     MissionEventListener::class,
        // ],

        // CommentCreated::class => [
        //     MissionEventListener::class,
        // ],

        // ArticleCreated::class => [
        //     MissionEventListener::class,
        // ],
    ];

    /**
     * Register any events for your application.
     *
     * @return void
     */
    public function boot()
    {
        //
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
