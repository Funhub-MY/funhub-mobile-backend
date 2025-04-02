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
        $schedule->command('send-custom-notification')->everyMinute()->onOneServer();
        $schedule->command('articles:run-engagements')->everyMinute()->onOneServer();

        // Every Five Minutes
		$schedule->command('article:publish')->everyFiveMinutes()->onOneServer();

        if (config('services.byteplus.enabled_vod') == true) {
            $schedule->command('byteplus:check-video-status')->everyFiveMinutes()->withoutOverlapping()->onOneServer();
        }

		// Every Ten Minutes
		$schedule->command('articles:sync-location-as-stores')->everyTenMinutes()->onOneServer();

        // Every Fifteen Minutes
        $schedule->command('merchant-offers:release')->everyFifteenMinutes()->onOneServer();
        $schedule->command('articles:sync-ratings-to-store-ratings')->everyFifteenMinutes()->withoutOverlapping()->onOneServer();
        if (config('services.bubble.status') == true) {
            $schedule->command('bubble:sync-user-store-ratings')->everyFifteenMinutes()->onOneServer();
        }

		// Every Thirty Minutes
		$schedule->command('city-names:populate')->everyThirtyMinutes()->onOneServer();
        if (config('app.auto_article_categories') == true) {
            $schedule->command('articles:categorize')->everyThirtyMinutes()->onOneServer();
        }

        // Hourly
        $schedule->command('fetch:news-feed')->hourly()->onOneServer();
        $schedule->command('media-partner:auto-publish-by-keywords')->hourly()->onOneServer();
        $schedule->job(new \App\Jobs\ImportedContactMatching())->hourly()->onOneServer();
        $schedule->command('stores:auto-hide-unonboarded')->hourly()->onOneServer();
		$schedule->command('redeem:send-review-reminder')->hourly()->onOneServer();

        // Daily at Specific Times
        $schedule->command('merchant-offers:publish')->dailyAt('00:00')->withoutOverlapping(expiresAt: 10)->onOneServer();
        $schedule->command('merchant-offers:auto-archieve')->dailyAt('00:05')->onOneServer();
        $schedule->command('article:auto-archive')->dailyAt('00:10')->onOneServer();
        $schedule->command('merchant-offers:send-expiring-notification')->dailyAt('10:08')->onOneServer();
        $schedule->command('merchant-offers:auto-move-vouchers-unsold')->dailyAt('00:35')->withoutOverlapping(10)->onOneServer();
        if (config('app.auto_redistribute_vouchers') == true) {
            $schedule->command('campaign:redistribute-quantities')->dailyAt('00:45')->onOneServer();
        }
		$schedule->command('articles:check-expired')->dailyAt('01:00')->onOneServer();

        // Every Two Hours
        $schedule->command('generate:article-views')->everyTwoHours()->onOneServer();
        $schedule->command('update:scheduled-views')->everyTwoHours()->onOneServer();

        // Daily
        $schedule->command('telescope:prune')->daily()->onOneServer();
        
        // Mixpanel Data Sync
        // $schedule->command('mixpanel:sync-voucher-sales')->dailyAt('02:00')->onOneServer();
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
