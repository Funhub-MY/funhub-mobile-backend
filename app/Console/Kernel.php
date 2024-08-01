<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     *
     * @param  \Illuminate\Console\Scheduling\Schedule  $schedule
     * @return void
     */
    protected function schedule(Schedule $schedule)
    {
        $schedule->command('telescope:prune')->daily();
        $schedule->command('fetch:news-feed')->hourly();

        $schedule->command('generate:article-views')->everyTwoHours();
        $schedule->command('update:scheduled-views')->everyTwoHours();

        // run every 15minutes for release merchant
        $schedule->command('merchant-offers:release')->everyFifteenMinutes();

        //run scheduled publish article every minute
        // $schedule->command('article:publish')->everyFiveMinutes();

        // run publish merchant offers every midnight
        $schedule->command('merchant-offers:publish')->dailyAt('00:00');
        $schedule->command('merchant-offers:auto-move-vouchers-unsold')->dailyAt('00:10');

        // run send notification merchant offer redemption is expiring
        $schedule->command('merchant-offers:send-expiring-notification')->dailyAt('00:00');

        // run auto archieve merchant offers every midnight 23:55
        $schedule->command('merchant-offers:auto-archieve')->dailyAt('00:00');

        // auto archive media partners articles that matches keywords
        $schedule->command('article:auto-archive')->dailyAt('00:00');

        // run every 5 mins to check for scheduled custom notifications
        $schedule->command('send-custom-notification')->everyMinute();

        // // run scheduled maintenance every hour to manage (DEPRECATED AS USING REMOTE CONFIG FROMN FIREBASWE)
        // $schedule->command('manage-maintenance-status')->hourly();

        // this will ensure city_id are correctly populated
        $schedule->command('city-names:populate')->everyThirtyMinutes(); // populate city names from locations

        // auto publish media partner article based on whitelist/blacklist keywords
        $schedule->command('media-partner:auto-publish-by-keywords')->hourly();

        // sync article ratings to store ratings
        $schedule->command('articles:sync-ratings-to-store-ratings')->everyFifteenMinutes();

        // run article engagements
        $schedule->command('articles:run-engagements')->everyMinute();

        // sync article tagged location as stores(un-onboarded stores)
        $schedule->command('articles:sync-location-as-stores')->hourly();

        // match contacts to users(related_user_id) in user_contacts table
        $schedule->job(new ImportedContactMatching())->hourly();

        // sync bubble contact for users, stores, and ratings
        if (config('services.bubble.status') == true) {
            $schedule->command('bubble:sync-user-store-ratings')->everyFifteenMinutes();
        }

        // categorize articles  every thiry minutes
        if (config('app.auto_article_categories') == true) {
            $schedule->command('articles:categorize')->everyThirtyMinutes();
        }

        $schedule->command('redeem:send-review-reminder')->dailyAt('10:00');

        // hide unonboarded stores without articles
        $schedule->command('stores:auto-hide-unonboarded')->hourly();
    }

    /**
     * Register the commands for the application.
     *
     * @return void
     */
    protected function commands()
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}
