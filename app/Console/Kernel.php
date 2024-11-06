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
        // Every Minute
        $schedule->command('send-custom-notification')->everyMinute();
        $schedule->command('articles:run-engagements')->everyMinute();

        // Every Five Minutes
        $schedule->command('article:publish')->everyFiveMinutes();
        $schedule->command('byteplus:check-video-status')->everyFiveMinutes()->withoutOverlapping();

        // Every Fifteen Minutes
        $schedule->command('merchant-offers:release')->everyFifteenMinutes();
        $schedule->command('articles:sync-ratings-to-store-ratings')->everyFifteenMinutes()->withoutOverlapping();
        if (config('services.bubble.status') == true) {
            $schedule->command('bubble:sync-user-store-ratings')->everyFifteenMinutes();
        }

        // Every Thirty Minutes
        $schedule->command('city-names:populate')->everyThirtyMinutes();
        if (config('app.auto_article_categories') == true) {
            $schedule->command('articles:categorize')->everyThirtyMinutes();
        }

        // Hourly
        $schedule->command('fetch:news-feed')->hourly();
        $schedule->command('media-partner:auto-publish-by-keywords')->hourly();
        $schedule->command('articles:sync-location-as-stores')->hourly();
        $schedule->job(new \App\Jobs\ImportedContactMatching())->hourly();
        $schedule->command('stores:auto-hide-unonboarded')->hourly();

        // Every Two Hours
        $schedule->command('generate:article-views')->everyTwoHours();
        $schedule->command('update:scheduled-views')->everyTwoHours();

        // Daily
        $schedule->command('telescope:prune')->daily();

        // Daily at Specific Times
        $schedule->command('merchant-offers:publish')->dailyAt('00:00')->withoutOverlapping(10);
        $schedule->command('merchant-offers:auto-archieve')->dailyAt('00:01');
        $schedule->command('article:auto-archive')->dailyAt('00:05');
        $schedule->command('merchant-offers:send-expiring-notification')->dailyAt('00:10');
        $schedule->command('merchant-offers:auto-move-vouchers-unsold')->dailyAt('00:15');
        $schedule->command('redeem:send-review-reminder')->dailyAt('10:00');
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
