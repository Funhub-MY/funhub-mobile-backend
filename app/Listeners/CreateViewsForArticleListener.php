<?php

namespace App\Listeners;

use Exception;
use App\Events\ArticleCreated;
use App\Models\BlacklistSeederUser;
use App\Models\Setting;
use App\Models\User;
use App\Models\ViewQueue;
use Illuminate\Support\Facades\Log;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Carbon\Carbon;

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

        // Check if the user associated with the article is blacklisted
        $user = $article->user;
        if ($user && $this->isUserBlacklisted($user)) {
            // User is blacklisted, do not generate view queue
            Log::info('[CreateViewsForArticleListener] User is blacklisted. Skipping view generation.', [
                'article_id' => $article->id,
                'user_id' => $user->id,
            ]);
            return;
        }

        $view_seeder_on = Setting::where('key', 'view_seeder_on')->first(); //check if view_seeder_on is 'true' or 'false'

        if ($view_seeder_on) {
            if (strtolower($view_seeder_on->value) === 'true') {
                $article = $event->article;

                //get the view_seeder_weight from settings table(view_seeder_weight is set by admin)
                $view_seeder_weight_setting = Setting::where('key', 'view_seeder_weight')->first();

                if ($view_seeder_weight_setting) {
                    Log::info('[CreateViewsForArticleListener] view_seeder_weight_setting: ', [
                        'key' => $view_seeder_weight_setting->key,
                        'value' => $view_seeder_weight_setting->value,
                    ]);
                    $value = $view_seeder_weight_setting->value;
                    try {
                        $auto_view_percentage = (int)$value; // Convert to integer
                        Log::info('[CreateViewsForArticleListener] auto_view_percentage: ', [
                            'auto_view_percentage' => $auto_view_percentage,
                        ]);
                    } catch (Exception $e) {
                        // Handle the case where the value is not convertible to an integer
                        $auto_view_percentage = 1; // Default as 1
                    }
                } else {
                    $auto_view_percentage = 1; // Default as 1
                }

                $total_app_user = User::count();

                //the highest point in the bell curve. (in hours)
                $peak_time = 6; 

                //Smaller values will result in a narrower curve, while larger values will make it wider.
                $standard_deviation = 3;

                // Calculate the total area under the bell curve from time 0 to 12
                $total_area_under_curve = 0;

                // Calculate the area under the curve for each 2-hour interval
                for ($time = 0; $time <= 12; $time += 2) {
                    $total_area_under_curve += $this->bellCurve($time, $peak_time, $standard_deviation);
                }

                // Calculate the desired total number of scheduled views based on a percentage of total users
                $total_desired_views = round($total_app_user * ($auto_view_percentage / 100));

                $total_accumulated_views = 0;

                // Create ViewQueue entries at 2-hour intervals
                for ($time = 0; $time <= 12; $time += 2) {
                    $view_percentage = $this->bellCurve($time, $peak_time, $standard_deviation) / $total_area_under_curve;
                    //make the total_desired_views equals to the area under the whole curve so that the percentage of the area under the curve will be equal to the percentage of the total_desired_views
                    $scheduled_views = round($total_desired_views * $view_percentage);
                    $total_accumulated_views += $scheduled_views;

                    // Ensure that the total accumulated views do not exceed the desired total
                    $scheduled_views = min($scheduled_views, $total_desired_views - $total_accumulated_views);
                    if($scheduled_views < 0) {
                        $scheduled_views = 0;
                    }

                    $scheduled_at = now()->addHours($time);

                    // Convert scheduled_at to Malaysia time (UTC+8)
                    $scheduled_at = $scheduled_at->setTimezone('Asia/Kuala_Lumpur');

                    // Check if the scheduled_at falls between 1 am and 6 am
                    if ($scheduled_at >= $scheduled_at->copy()->startOfDay()->addHours(1) && $scheduled_at <= $scheduled_at->copy()->startOfDay()->addHours(2)) {
                        // Adjust the scheduled_at to be 6 am
                        $scheduled_at = $scheduled_at->copy()->startOfDay()->addHours(6);
                    } elseif ($scheduled_at >= $scheduled_at->copy()->startOfDay()->addHours(2) && $scheduled_at <= $scheduled_at->copy()->startOfDay()->addHours(3)) {
                        // Adjust the scheduled_at to be 7 am
                        $scheduled_at = $scheduled_at->copy()->startOfDay()->addHours(7);
                    } elseif ($scheduled_at >= $scheduled_at->copy()->startOfDay()->addHours(3) && $scheduled_at <= $scheduled_at->copy()->startOfDay()->addHours(4)) {
                        // Adjust the scheduled_at to be 8 am
                        $scheduled_at = $scheduled_at->copy()->startOfDay()->addHours(8);
                    } elseif ($scheduled_at >= $scheduled_at->copy()->startOfDay()->addHours(4) && $scheduled_at <= $scheduled_at->copy()->startOfDay()->addHours(5)) {
                        // Adjust the scheduled_at to be 9 am
                        $scheduled_at = $scheduled_at->copy()->startOfDay()->addHours(9);
                    } elseif ($scheduled_at >= $scheduled_at->copy()->startOfDay()->addHours(5) && $scheduled_at <= $scheduled_at->copy()->startOfDay()->addHours(6)) {
                        // Adjust the scheduled_at to be 10 am
                        $scheduled_at = $scheduled_at->copy()->startOfDay()->addHours(10);
                    }

                    Log::info('[CreateViewsForArticleListener]', [
                        'auto_view_percentage' => $auto_view_percentage,
                        'total_app_user' => $total_app_user,
                        'view_percentage' => $view_percentage,
                        'article_id' => $article->id,
                        'scheduled_views' => $scheduled_views,
                        'scheduled_at' => $scheduled_at,
                    ]);

                    // Create a ViewQueue entry
                    ViewQueue::create([
                        'article_id' => $article->id,
                        'scheduled_views' => $scheduled_views,
                        'scheduled_at' => $scheduled_at,
                    ]);
                }
            }
        } 
    }

    // Function to calculate the view percentage using a bell curve
    protected function bellCurve($x, $peak, $stdDev) {
        $exponent = -0.5 * (($x - $peak) / $stdDev) ** 2;
        return (1 / ($stdDev * sqrt(2 * M_PI))) * exp($exponent);
    }

    private function isUserBlacklisted($user)
    {
        return BlacklistSeederUser::where('user_id', $user->id)->exists();
    }
}







// public function handle(ArticleCreated $event)
// {
//     $view_seeder_on = Setting::where('key', 'view_seeder_on')->first(); //check if view_seeder_on is 'true' or 'false'

//     if ($view_seeder_on) {
//         if (strtolower($view_seeder_on->value) === 'true') {
//             $article = $event->article;

//             //get the view_seeder_weight from settings table(view_seeder_weight is set by admin)
//             $view_seeder_weight_setting = Setting::where('key', 'view_seeder_weight')->first();

//             if ($view_seeder_weight_setting) {
//                 Log::info('[CreateViewsForArticleListener] view_seeder_weight_setting: ', [
//                     'key' => $view_seeder_weight_setting->key,
//                     'value' => $view_seeder_weight_setting->value,
//                 ]);
//                 $value = $view_seeder_weight_setting->value;
//                 try {
//                     $auto_view_percentage = (int)$value; // Convert to integer
//                     Log::info('[CreateViewsForArticleListener] auto_view_percentage: ', [
//                         'auto_view_percentage' => $auto_view_percentage,
//                     ]);
//                 } catch (\Exception $e) {
//                     // Handle the case where the value is not convertible to an integer
//                     $auto_view_percentage = 1; // Default as 1
//                 }
//             } else {
//                 $auto_view_percentage = 1; // Default as 1
//             }

//             $total_app_user = User::count();

//             //the highest point in the bell curve. (in hours)
//             $peak_time = 12; 

//             //the highest point in the bell curve. Smaller values will result in a narrower curve, while larger values will make it wider.
//             $standard_deviation = 3;

//             $scale_up_by = 100;

//             // Create ViewQueue entries at 2-hour intervals
//             for ($time = 0; $time <= 24; $time += 2) {
//                 // Calculate the view percentage using a bell curve distribution
//                 $view_percentage = $this->bellCurve($time, $peak_time, $standard_deviation); //value too small,if round off will be 0,so need to scale up
//                 $scaled_view_percentage = $view_percentage * $scale_up_by;

//                 // Calculate the number of scheduled views directly, no decimal 
//                 $scheduled_views = round(($total_app_user * $auto_view_percentage / 100) * $scaled_view_percentage);

//                 Log::info('[CreateViewsForArticleListener]', [
//                     'auto_view_percentage' => $auto_view_percentage,
//                     'total_app_user' => $total_app_user,
//                     'view_percentage' => $view_percentage,
//                     'scaled_view_percentage' => $scaled_view_percentage,
//                     'article_id' => $article->id,
//                     'scheduled_views' => $scheduled_views,
//                     'scheduled_at' => now()->addHours($time),
//                 ]);

//                 // Create a ViewQueue entry
//                 ViewQueue::create([
//                     'article_id' => $article->id,
//                     'scheduled_views' => $scheduled_views,
//                     'scheduled_at' => now()->addHours($time),
//                 ]);
//             }
//         }
//     } 
// }