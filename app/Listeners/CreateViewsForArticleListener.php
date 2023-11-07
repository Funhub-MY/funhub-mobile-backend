<?php

namespace App\Listeners;

use App\Events\ArticleCreated;
use App\Models\Setting;
use App\Models\User;
use App\Models\ViewQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class CreateViewsForArticleListener
{
    /**
     * Create the event listener.
     *
     * @return void
     */
    public function __construct()
    {
        //
    }

    /**
     * Handle the event.
     *
     * @param  object  $event
     * @return void
     */
    public function handle(ArticleCreated $event)
    {
        $article = $event->article;

        //get the view_seeder_weight from settings table(view_seeder_weight is set by admin)
        $setting = Setting::where('key', 'view_seeder_weight')->first();

        if ($setting) {
            $auto_view_percentage = $setting->value;
        } else {
            $auto_view_percentage = 10; //default as 10 first
        }

        $total_app_user = User::count();
        
        //the highest point in the bell curve. (in hours)
        $peak_time = 12; 

        //the highest point in the bell curve. Smaller values will result in a narrower curve, while larger values will make it wider.
        $standard_deviation = 3;

        $scale_up_by = 100;

        // Create ViewQueue entries at 2-hour intervals
        for ($time = 0; $time <= 24; $time += 2) {
            // Calculate the view percentage using a bell curve distribution
            $view_percentage = $this->bellCurve($time, $peak_time, $standard_deviation); //value too small,if round off will be 0,so need to scale up
            $scaled_view_percentage = $view_percentage * $scale_up_by;

            // Calculate the number of scheduled views directly, no decimal 
            $scheduled_views = round(($total_app_user * $auto_view_percentage / 100) * $scaled_view_percentage);

            // Create a ViewQueue entry
            ViewQueue::create([
                'article_id' => $article->id,
                'scheduled_views' => $scheduled_views,
                'scheduled_at' => now()->addHours($time),
            ]);
        }
    }

    // Function to calculate the view percentage using a bell curve
    protected function bellCurve($x, $peak, $stdDev) {
        $exponent = -0.5 * (($x - $peak) / $stdDev) ** 2;
        return (1 / ($stdDev * sqrt(2 * M_PI))) * exp($exponent);
    }
}
