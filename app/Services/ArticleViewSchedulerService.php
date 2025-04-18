<?php

namespace App\Services;

use App\Models\Article;
use App\Models\Setting;
use App\Models\User;
use App\Models\ViewQueue;
use App\Models\BlacklistSeederUser;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class ArticleViewSchedulerService
{
    /**
     * Schedule automated views for an article based on settings and user count.
     *
     * @param Article $article The article to schedule views for.
     * @return void
     */
    public function scheduleViews(Article $article): void
    {
        // Check if the user associated with the article is blacklisted
        $user = $article->user;
        if ($user && $this->isUserBlacklisted($user)) {
            // User is blacklisted, do not generate view queue
            Log::info('[ArticleViewSchedulerService] User is blacklisted. Skipping view generation.', [
                'article_id' => $article->id,
                'user_id' => $user->id,
            ]);
            return;
        }

        $view_seeder_on = Setting::where('key', 'view_seeder_on')->first(); //check if view_seeder_on is 'true' or 'false'

        if (!$view_seeder_on || strtolower($view_seeder_on->value) !== 'true') {
            Log::info('[ArticleViewSchedulerService] View seeder is off. Skipping view generation.', ['article_id' => $article->id]);
            return;
        }

        // Get the view_seeder_weight from settings table
        $view_seeder_weight_setting = Setting::where('key', 'view_seeder_weight')->first();
        $auto_view_percentage = 1; // Default
        if ($view_seeder_weight_setting) {
            try {
                $auto_view_percentage = (int)$view_seeder_weight_setting->value; // Convert to integer
                Log::info('[ArticleViewSchedulerService] view_seeder_weight found.', ['value' => $auto_view_percentage]);
            } catch (\Exception $e) {
                Log::error('[ArticleViewSchedulerService] Failed to convert view_seeder_weight to integer.', ['value' => $view_seeder_weight_setting->value, 'error' => $e->getMessage()]);
                // Use default $auto_view_percentage = 1;
            }
        }

        $total_app_user = User::count();
        
        // Bell curve parameters
        $peak_time = 6; // the highest point in the bell curve (in hours)
        $standard_deviation = 3; // Smaller values = narrower curve, larger = wider.

        // Calculate the total area under the bell curve from time 0 to 12
        $total_area_under_curve = 0;
        for ($time = 0; $time <= 12; $time += 2) {
            $total_area_under_curve += $this->bellCurve($time, $peak_time, $standard_deviation);
        }

        if ($total_area_under_curve == 0) {
             Log::error('[ArticleViewSchedulerService] Total area under curve is zero, cannot calculate view percentage.', ['article_id' => $article->id]);
             return; // Avoid division by zero
        }

        // Calculate the desired total number of scheduled views based on a percentage of total users
        $total_desired_views = round($total_app_user * ($auto_view_percentage / 100));

        $total_accumulated_views = 0;

        Log::info('[ArticleViewSchedulerService] Starting view scheduling.', [
            'article_id' => $article->id,
            'auto_view_percentage' => $auto_view_percentage,
            'total_app_user' => $total_app_user,
            'total_desired_views' => $total_desired_views
        ]);

        // Create ViewQueue entries at 2-hour intervals
        for ($time = 0; $time <= 12; $time += 2) {
            $view_percentage = $this->bellCurve($time, $peak_time, $standard_deviation) / $total_area_under_curve;
            $scheduled_views = round($total_desired_views * $view_percentage);

            // Adjust accumulated views
            $remaining_views = $total_desired_views - $total_accumulated_views;
            $scheduled_views = min($scheduled_views, $remaining_views);
            if($scheduled_views < 0) {
                $scheduled_views = 0;
            }
            // Ensure on the last iteration, we assign any remaining views due to rounding
            if ($time == 12) {
                 $scheduled_views = $remaining_views; 
            }

            $total_accumulated_views += $scheduled_views;

            $scheduled_at = now()->addHours($time);

            // Convert scheduled_at to Malaysia time (UTC+8) and adjust early morning times
            $scheduled_at = $this->adjustScheduleTime($scheduled_at);

            Log::info('[ArticleViewSchedulerService] Creating ViewQueue entry.', [
                'article_id' => $article->id,
                'time_offset_hours' => $time,
                'view_percentage' => $view_percentage,
                'calculated_views' => round($total_desired_views * $view_percentage), // Log calculated before adjustment
                'scheduled_views' => $scheduled_views,
                'scheduled_at' => $scheduled_at->toIso8601String(),
                'total_accumulated_views' => $total_accumulated_views
            ]);

            // Create a ViewQueue entry if views > 0
            if ($scheduled_views > 0) {
                try {
                    ViewQueue::create([
                        'article_id' => $article->id,
                        'scheduled_views' => $scheduled_views,
                        'scheduled_at' => $scheduled_at,
                        'is_processed' => false, // Ensure this is set
                    ]);
                } catch (\Exception $e) {
                     Log::error('[ArticleViewSchedulerService] Failed to create ViewQueue entry.', [
                        'article_id' => $article->id,
                        'scheduled_views' => $scheduled_views,
                        'scheduled_at' => $scheduled_at->toIso8601String(),
                        'error' => $e->getMessage()
                    ]);
                }
            }
        }
        Log::info('[ArticleViewSchedulerService] Finished scheduling views.', ['article_id' => $article->id, 'total_scheduled_views' => $total_accumulated_views]);
    }

    /**
     * Adjusts the scheduled time to avoid early morning hours (1 AM - 5:59 AM).
     *
     * @param Carbon $scheduled_at
     * @return Carbon
     */
    protected function adjustScheduleTime(Carbon $scheduled_at): Carbon
    {
        $scheduled_at_local = $scheduled_at->copy()->setTimezone('Asia/Kuala_Lumpur');
        $hour = (int)$scheduled_at_local->format('H');

        if ($hour >= 1 && $hour < 6) {
            $targetHour = 0;
            switch ($hour) {
                case 1: $targetHour = 6; break;
                case 2: $targetHour = 7; break;
                case 3: $targetHour = 8; break;
                case 4: $targetHour = 9; break;
                case 5: $targetHour = 10; break;
            }
            // Set the time to the target hour, keeping the original date
            $adjusted_time = $scheduled_at_local->copy()->startOfDay()->addHours($targetHour);
            Log::debug('[ArticleViewSchedulerService] Adjusting schedule time.', ['from' => $scheduled_at_local->toIso8601String(), 'to' => $adjusted_time->toIso8601String()]);
            return $adjusted_time; // Return adjusted time in Asia/Kuala_Lumpur
        }

        return $scheduled_at; // Return original time (which might be UTC or adjusted TZ)
    }

    /**
     * Calculates the value on a bell curve (normal distribution).
     *
     * @param float $x Value
     * @param float $peak Mean (peak of the curve)
     * @param float $stdDev Standard Deviation (spread of the curve)
     * @return float
     */
    protected function bellCurve(float $x, float $peak, float $stdDev): float
    {
        if ($stdDev == 0) return 0; // Avoid division by zero
        $exponent = -0.5 * (($x - $peak) / $stdDev) ** 2;
        return (1 / ($stdDev * sqrt(2 * M_PI))) * exp($exponent);
    }

    /**
     * Check if a user is blacklisted for view seeding.
     *
     * @param User $user
     * @return bool
     */
    private function isUserBlacklisted(User $user): bool
    {
        return BlacklistSeederUser::where('user_id', $user->id)->exists();
    }
}
