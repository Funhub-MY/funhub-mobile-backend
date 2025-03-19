<?php

namespace App\Listeners;

use App\Events\CompletedProfile;
use App\Models\User;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;
use App\Events\UserSettingsUpdated;

class UserSettingsSavedListener
{
    /**
     * Create the event listener.
     *
     * @return void
     */
    public function __construct()
    {
    }

    /**
     * Handle the event.
     *
     * @param  object  $event
     * @return void
     */
    public function handle(UserSettingsUpdated $event)
    {
        $user = $event->user;
        // check if name, email, interests, birthday, gender are all saved
        if ($user->name
            && $user->email
            && $user->avatar
            && $user->disableCache()->articleCategoriesInterests()->count() > 0
            && $user->dob)
        {
            Log::info('User profile completed', [
                'user_id' => $user->id,
            ]);

            // fire the event
            event(new CompletedProfile($user));
        } else {
            Log::info('User profile not completed', [
                'user_id' => $user->id,
                'user_name' => $user->name,
                'user_email' => $user->email,
                'avatar_id' => $user->avatar,
                'user_interests' => $user->disableCache()->articleCategoriesInterests()->count(),
                'user_dob' => $user->dob,
            ]);
        }
    }
}
