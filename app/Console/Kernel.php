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
        
        $schedule->command('generate:article-views')->hourly();

        // run every 15minutes for release merchant
        $schedule->command('merchant-offers:release')->everyFifteenMinutes();

        //run scheduled publish article every minute
        $schedule->command('article:publish')->everyFiveMinutes();

        // run publish merchant offers every midnight
        $schedule->command('merchant-offers:publish')->dailyAt('00:00');

        // run auto archieve merchant offers every midnight 23:55
        $schedule->command('merchant-offers:auto-archieve')->dailyAt('00:00');
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
