<?php

namespace App\Listeners;

use Exception;
use App\Events\MissionCompletedEvent;
use App\Models\Mission;
use App\Notifications\CompletedNewbieMission;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;

class CheckNewbieMissionsCompletedListener implements ShouldQueue
{
    use InteractsWithQueue;

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
     * @param MissionCompletedEvent $event
     * @return void
     */
    public function handle(MissionCompletedEvent $event)
    {
        $user = $event->user;
        
        // Skip if user has already received the notification
        if ($user->newbie_missions_completed_at) {
            return;
        }
        
        // Get count of all enabled one-off missions
        $totalOneOffMissions = Mission::where('frequency', 'one-off')
            ->where('status', 1)
            ->count();
            
        // Get count of completed one-off missions for this user
        $completedOneOffMissions = $user->missionsParticipating()
            ->wherePivot('is_completed', true)
            ->where('frequency', 'one-off')
            ->where('status', 1)
            ->count();
        
        // If all one-off missions are completed, send notification
        if ($completedOneOffMissions >= $totalOneOffMissions && $totalOneOffMissions > 0) {
            try {
                // Update user to mark that they've received the notification
                $user->newbie_missions_completed_at = now();
                $user->save();
                
                // Send notification
                $user->notify(new CompletedNewbieMission($user));
                
                Log::info('User completed all one-off missions', [
                    'user_id' => $user->id,
                    'total_missions' => $totalOneOffMissions,
                    'completed_missions' => $completedOneOffMissions
                ]);
            } catch (Exception $e) {
                Log::error('Failed to send completed newbie mission notification', [
                    'user_id' => $user->id,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);
            }
        } else {
            Log::info('User has not completed all one-off missions', [
                'user_id' => $user->id,
                'total_missions' => $totalOneOffMissions,
                'completed_missions' => $completedOneOffMissions
            ]);
        }
    }
}
